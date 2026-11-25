#include <algorithm>
#include <bit>
#include <cmath>
#include <cstdint>
#include <cstring>
#include <iomanip>
#include <iostream>
#include <limits>
#include <map>
#include <random>
#include <sstream>
#include <stdexcept>
#include <string>
#include <type_traits>
#include <sys/resource.h>
#include <sys/prctl.h>
#include <unistd.h>
#include <vector>
#include <zlib.h>

namespace {
using UnitId = uint32_t;
constexpr auto noUnit = std::numeric_limits<UnitId>::max();
constexpr uint32_t checkpointMagic = 0x32435247;
constexpr rlim_t nativeMemoryBudget = 512ULL * 1024 * 1024;
static_assert(std::endian::native == std::endian::little);
static_assert(sizeof(double) == 8 && std::numeric_limits<double>::is_iec559);

void require(bool condition)
{
    if (!condition) {
        throw std::runtime_error("Données du moteur de combat incohérentes.");
    }
}

template <typename... Fields>
void read(Fields&... fields)
{
    require(static_cast<bool>((std::cin >> ... >> fields)));
}

// Ces tirages doivent consommer le même flux que Randomizer::getInt en PHP, même
// lorsque les bornes sont égales. Un std::uniform_int_distribution serait différent.
class Random {
public:
    std::mt19937 engine;

    explicit Random(uint32_t seed) : engine(seed) {}

    uint64_t zeroTo(uint64_t max)
    {
        if (max <= UINT32_MAX) {
            return draw<uint32_t>(max, [&] { return static_cast<uint32_t>(engine()); });
        }
        return draw<uint64_t>(max, [&] {
            const auto low = static_cast<uint64_t>(engine());
            return low | (static_cast<uint64_t>(engine()) << 32);
        });
    }

    uint64_t between(uint64_t min, uint64_t max)
    {
        return min + zeroTo(max - min);
    }

private:
    template <typename Int, typename Generator>
    static Int draw(Int max, Generator next)
    {
        auto raw = next();
        constexpr Int all = std::numeric_limits<Int>::max();
        if (max == all) {
            return raw;
        }

        const Int count = max + 1;
        if (std::has_single_bit(count)) {
            return raw & max;
        }

        // Rejet modulo avec la même borne que PHP (ext/random/random.c).
        const Int ceiling = all - all % count - 1;
        while (raw > ceiling) {
            raw = next();
        }
        return raw % count;
    }
};

// Une Fenwick sur les blocs de 64 bits compte les unités disponibles. Le rang
// dans le bitmap restitue l'ordre exact des candidats sans les recopier au tir.
class RankBits {
public:
    explicit RankBits(UnitId size)
        : words((uint64_t{size} + 63) / 64), tree(words.size() + 1) {}

    UnitId total = 0;

    bool get(UnitId index) const
    {
        return ((words[index / 64] >> (index % 64)) & 1) != 0;
    }

    void set(UnitId index, bool enabled)
    {
        auto& word = words[index / 64];
        const auto mask = uint64_t{1} << (index % 64);
        if (bool(word & mask) == enabled) {
            return;
        }

        word ^= mask;
        total = enabled ? total + 1 : total - 1;
        for (auto node = std::size_t{index / 64} + 1; node < tree.size(); node += node & -node) {
            tree[node] = enabled ? tree[node] + 1 : tree[node] - 1;
        }
    }

    void setWord(std::size_t word, uint64_t bits)
    {
        words[word] = bits;
    }

    void build()
    {
        total = 0;
        std::fill(tree.begin(), tree.end(), 0);
        for (std::size_t node = 1; node < tree.size(); ++node) {
            const auto count = std::popcount(words[node - 1]);
            tree[node] += count;
            total += count;
            const auto parent = node + (node & -node);
            if (parent < tree.size()) {
                tree[parent] += tree[node];
            }
        }
    }

    UnitId select(UnitId rank) const
    {
        std::size_t word = 0;
        for (auto step = std::bit_floor(words.size()); step != 0; step >>= 1) {
            const auto next = word + step;
            if (next < tree.size() && tree[next] <= rank) {
                word = next;
                rank -= tree[next];
            }
        }
        auto bits = words[word];
        while (rank-- != 0) {
            bits &= bits - 1;
        }
        return static_cast<UnitId>(word * 64 + std::countr_zero(bits));
    }

private:
    std::vector<uint64_t> words;
    std::vector<UnitId> tree;
};

struct Group {
    std::string key;
    unsigned role = 0, camp = 0, category = 0, tier = 0;
    unsigned owner = 0, scoreOwner = 0;
    unsigned arrival = 0, shotsMin = 0, shotsMax = 0, secondMin = 0, secondMax = 0, secondCategory = 0;
    int64_t secondPrecision = 0;
    UnitId quantity = 0, start = 0;
    double health = 0, shield = 0, attack = 0, precision = 0, tracking = 0, evasion = 0, secondAttack = 0;
    uint64_t weight = 0;
    std::vector<double> extraChances;
    std::vector<double> counters;
    RankBits alive{0}, wounded{0};

    void rebuildIndexes(const std::vector<double>& hp, const std::vector<double>& shields)
    {
        for (uint64_t offset = 0; offset < quantity; offset += 64) {
            uint64_t living = 0;
            uint64_t damaged = 0;
            for (unsigned bit = 0; bit < 64 && offset + bit < quantity; ++bit) {
                const auto unit = start + offset + bit;
                if (hp[unit] > 0) {
                    living |= uint64_t{1} << bit;
                    if (hp[unit] < health || shields[unit] < shield) {
                        damaged |= uint64_t{1} << bit;
                    }
                }
            }
            alive.setWord(offset / 64, living);
            wounded.setWord(offset / 64, damaged);
        }
        alive.build();
        wounded.build();
    }
};

struct GroupStats {
    uint64_t shots = 0, hits = 0, lost = 0, targeted = 0;
    double damage = 0, shieldDamage = 0, structureDamage = 0;
};

struct Candidate {
    unsigned group;
    double factor, chance;
};

class CheckpointFile {
public:
    CheckpointFile(const std::string& path, const char* mode) : file(gzopen(path.c_str(), mode))
    {
        require(file != nullptr);
    }

    ~CheckpointFile()
    {
        if (file != nullptr) {
            gzclose(file);
        }
    }

    template <typename T>
    void put(const T& value)
    {
        static_assert(std::is_trivially_copyable_v<T>);
        bytesOut(&value, sizeof(T));
    }

    template <typename T>
    T get()
    {
        static_assert(std::is_trivially_copyable_v<T>);
        T value{};
        bytesIn(&value, sizeof(T));
        return value;
    }

    void bytesOut(const void* data, std::size_t length)
    {
        const auto* cursor = static_cast<const char*>(data);
        while (length != 0) {
            const auto chunk = static_cast<unsigned>(std::min<std::size_t>(length, 65536));
            require(gzwrite(file, cursor, chunk) == static_cast<int>(chunk));
            cursor += chunk;
            length -= chunk;
        }
    }

    void bytesIn(void* data, std::size_t length)
    {
        auto* cursor = static_cast<char*>(data);
        while (length != 0) {
            const auto chunk = static_cast<unsigned>(std::min<std::size_t>(length, 65536));
            require(gzread(file, cursor, chunk) == static_cast<int>(chunk));
            cursor += chunk;
            length -= chunk;
        }
    }

    void endOfFile()
    {
        require(gzgetc(file) == -1 && gzeof(file) != 0);
    }

    void close()
    {
        auto* contents = file;
        file = nullptr;
        require(gzclose(contents) == Z_OK);
    }

private:
    gzFile file;
};

class Combat {
public:
    Combat()
    {
        std::string protocol;
        unsigned groupsCount, ownerCount, scoreOwnerCount;
        read(protocol, seed, maxRounds, roundLimit, recharge, independent, restore, groupsCount, ownerCount, scoreOwnerCount, importedRound);
        require(protocol == "GR-COMBAT-2" && maxRounds >= 1 && maxRounds <= 100 && roundLimit >= 1 && roundLimit <= maxRounds
            && std::isfinite(recharge) && recharge >= 0 && recharge <= 100 && groupsCount >= 1 && groupsCount <= 4096
            && ownerCount <= groupsCount && scoreOwnerCount <= groupsCount && importedRound < roundLimit);
        random.engine.seed(seed);
        ownerSize = ownerCount;
        scoreSize = scoreOwnerCount;
        groups.reserve(groupsCount);
        uint64_t largestSum = 0;
        for (unsigned i = 0; i < groupsCount; ++i) {
            Group group;
            unsigned extras;
            read(group.key, group.owner, group.scoreOwner, group.role, group.camp, group.category,
                group.tier, group.arrival, group.quantity, group.health, group.shield, group.attack,
                group.precision, group.tracking, group.evasion, group.weight, group.shotsMin, group.shotsMax,
                group.secondAttack, group.secondMin, group.secondMax, group.secondPrecision, group.secondCategory, extras);
            require(!group.key.empty() && group.key.size() <= 2048 && group.quantity != 0 && group.owner < ownerCount
                && group.scoreOwner <= scoreOwnerCount && group.role <= 2 && group.tier >= 1 && group.tier <= 3
                && group.arrival >= 1 && group.arrival <= maxRounds && group.shotsMax >= group.shotsMin
                && group.shotsMax <= 100 && group.secondMax >= group.secondMin && group.secondMax <= 100
                && extras <= 256 && group.weight > 0 && group.health > 0 && group.shield >= 0 && group.attack >= 0);
            require(group.weight <= (uint64_t{INT64_MAX} - largestSum) / group.quantity
                && group.quantity < noUnit - size);
            largestSum += group.quantity * group.weight;
            group.extraChances.resize(extras);
            for (auto& value : group.extraChances) {
                read(value);
                require(value >= 0 && value <= 1);
            }
            group.start = size;
            size += group.quantity;
            group.alive = RankBits(group.quantity);
            group.wounded = RankBits(group.quantity);
            require(groupByKey.emplace(group.key, groups.size()).second);
            groups.push_back(std::move(group));
        }
        for (auto& group : groups) {
            group.counters.resize(groups.size());
            for (auto& value : group.counters) {
                read(value);
                require(value > 0 && value <= 10);
            }
        }

        hp.resize(size);
        shields.resize(size);
        for (const auto& group : groups) {
            std::fill_n(hp.begin() + group.start, group.quantity, group.health);
            std::fill_n(shields.begin() + group.start, group.quantity, group.shield);
        }
        if (importedRound != 0) {
            // Un combat commencé par le moteur PHP peut dépasser le seuil à
            // l'arrivée de nouveaux renforts, sans rejouer les rounds persistés.
            completedRound = importedRound;
            read(random.engine);
            unsigned changed;
            read(changed);
            require(changed <= size);
            for (unsigned i = 0; i < changed; ++i) {
                unsigned index;
                UnitId slot;
                double health, shield;
                read(index, slot, health, shield);
                require(index < groups.size());
                const auto& group = groups[index];
                require(group.arrival <= importedRound && slot < group.quantity);
                checkDurability(group, health, shield);
                hp[group.start + slot] = health;
                shields[group.start + slot] = shield;
            }
        }
    }

    void restoreCheckpoint(const std::string& path)
    {
        require(importedRound == 0);
        CheckpointFile file(path, "rb");
        require(file.get<uint32_t>() == checkpointMagic && file.get<uint32_t>() == seed);
        completedRound = file.get<uint32_t>();
        require(completedRound > 0 && completedRound < roundLimit);
        const auto randomLength = file.get<uint32_t>();
        require(randomLength > 0 && randomLength < 8192);
        std::string randomData(randomLength, '\0');
        file.bytesIn(randomData.data(), randomLength);
        std::istringstream randomState(randomData);
        require(static_cast<bool>(randomState >> random.engine));

        // L'identité d'une cohorte est stable même quand une nouvelle flotte
        // s'insère avant elle dans l'ordre de tir.
        const auto checkpointGroups = file.get<uint32_t>();
        require(checkpointGroups <= groups.size());
        std::vector<bool> restored(groups.size());
        for (unsigned i = 0; i < checkpointGroups; ++i) {
            const auto keySize = file.get<uint32_t>();
            require(keySize > 0 && keySize <= 2048);
            std::string key(keySize, '\0');
            file.bytesIn(key.data(), key.size());
            const auto found = groupByKey.find(key);
            require(found != groupByKey.end() && !restored[found->second]);
            const auto& group = groups[found->second];
            require(group.arrival <= completedRound && file.get<UnitId>() == group.quantity);
            const auto bytes = sizeof(double) * std::size_t{group.quantity};
            file.bytesIn(hp.data() + group.start, bytes);
            file.bytesIn(shields.data() + group.start, bytes);
            for (UnitId slot = group.start; slot < group.start + group.quantity; ++slot) {
                checkDurability(group, hp[slot], shields[slot]);
            }
            restored[found->second] = true;
        }
        for (unsigned i = 0; i < groups.size(); ++i) {
            require(restored[i] == (groups[i].arrival <= completedRound));
        }
        file.endOfFile();
        file.close();
    }

    void fight(const std::string& savePath)
    {
        for (auto& group : groups) {
            group.rebuildIndexes(hp, shields);
        }
        std::string outcome = "draw";
        int winner = -1;
        std::cout << std::setprecision(std::numeric_limits<double>::max_digits10);
        for (unsigned round = completedRound + 1; round <= roundLimit; ++round) {
            playRound(round);
            completedRound = round;
            std::tie(outcome, winner) = classify(round);
            if (outcome != "ongoing") {
                break;
            }
        }

        std::cout << "E " << completedRound << ' ' << outcome << ' ' << winner << '\n';
        for (unsigned i = 0; i < groups.size(); ++i) {
            const auto& group = groups[i];
            double healthMin = group.health, healthMax = 0;
            double shieldMin = group.shield, shieldMax = 0;
            if (group.alive.total != 0) {
                for (UnitId unit = group.start; unit < group.start + group.quantity; ++unit) {
                    if (hp[unit] > 0) {
                        healthMin = std::min(healthMin, hp[unit]);
                        healthMax = std::max(healthMax, hp[unit]);
                        const auto shield = restore ? group.shield : shields[unit];
                        shieldMin = std::min(shieldMin, shield);
                        shieldMax = std::max(shieldMax, shield);
                    }
                }
            }
            std::cout << "V " << i << ' ' << group.alive.total << ' ' << healthMin << ' ' << healthMax << ' '
                << shieldMin << ' ' << shieldMax << '\n';
        }
        if (outcome == "ongoing" && savePath != "-") {
            saveCheckpoint(savePath);
        }
    }

private:
    uint32_t seed;
    unsigned maxRounds, roundLimit, completedRound = 0, importedRound, ownerSize, scoreSize;
    bool independent, restore;
    double recharge;
    UnitId size = 0;
    Random random{0};
    std::vector<Group> groups;
    std::map<std::string, unsigned> groupByKey;
    std::vector<double> hp, shields;

    static void checkDurability(const Group& group, double hp, double shield)
    {
        require(hp >= 0 && hp <= group.health && shield >= 0 && shield <= group.shield);
    }

    void saveCheckpoint(const std::string& path)
    {
        CheckpointFile file(path, "wb1");
        file.put(checkpointMagic);
        file.put(seed);
        file.put(static_cast<uint32_t>(completedRound));
        std::ostringstream output;
        output << random.engine;
        const auto randomData = output.str();
        file.put(static_cast<uint32_t>(randomData.size()));
        file.bytesOut(randomData.data(), randomData.size());
        file.put(static_cast<uint32_t>(std::count_if(groups.begin(), groups.end(),
            [&](const auto& group) { return group.arrival <= completedRound; })));
        for (const auto& group : groups) {
            if (group.arrival > completedRound) {
                continue;
            }
            file.put(static_cast<uint32_t>(group.key.size()));
            file.bytesOut(group.key.data(), group.key.size());
            file.put(group.quantity);
            const auto bytes = sizeof(double) * std::size_t{group.quantity};
            file.bytesOut(hp.data() + group.start, bytes);
            file.bytesOut(shields.data() + group.start, bytes);
        }
        file.close();
    }

    unsigned groupOf(UnitId unit) const
    {
        return static_cast<unsigned>(
            std::upper_bound(groups.begin(), groups.end(), unit, [](auto key, const auto& group) { return key < group.start; })
            - groups.begin() - 1);
    }

    bool hostile(const Group& attacker, const Group& target) const
    {
        if (independent) {
            return attacker.camp != target.camp;
        }
        return attacker.role == 0 ? (target.role != 0 || target.camp != attacker.camp) : target.role == 0;
    }

    static double hitChance(const Group& attacker, const Group& target, int64_t precisionModifier, double counter)
    {
        const auto base = std::clamp(attacker.precision + attacker.tracking + precisionModifier - target.evasion - 15, 15.0, 95.0);
        const auto effect = std::clamp(base * counter, 15.0, 95.0);
        return std::clamp(effect + 30 * (static_cast<int>(attacker.tier) - static_cast<int>(target.tier)), 15.0, 95.0);
    }

    const Candidate* choose(const std::vector<Candidate>& candidates, UnitId& unit)
    {
        bool priority = true;
        uint64_t total = 0;
        for (const auto& candidate : candidates) {
            const auto& group = groups[candidate.group];
            total += group.weight * group.wounded.total;
        }
        if (total == 0) {
            priority = false;
            for (const auto& candidate : candidates) {
                const auto& group = groups[candidate.group];
                total += group.weight * group.alive.total;
            }
        }
        if (total == 0) {
            return nullptr;
        }
        auto draw = random.zeroTo(total - 1);
        for (const auto& candidate : candidates) {
            const auto& group = groups[candidate.group];
            const auto& index = priority ? group.wounded : group.alive;
            const auto weight = group.weight * index.total;
            if (draw < weight) {
                unit = group.start + index.select(static_cast<UnitId>(draw / group.weight));
                return &candidate;
            }
            draw -= weight;
        }
        throw std::logic_error("Cible pondérée introuvable.");
    }

    unsigned shotCount(const Group& attacker, unsigned min, unsigned max)
    {
        const auto base = static_cast<unsigned>(random.between(min, max));
        unsigned shots = base;
        // L'ancien moteur tire toutes les chances supplémentaires avant la
        // première cible: différer ces tirages changerait le déroulement.
        for (unsigned i = 0; i < base; ++i) {
            for (const auto chance : attacker.extraChances) {
                if (random.between(1, 10000) <= chance * 10000) {
                    ++shots;
                }
            }
        }
        return shots;
    }

    void fire(const Candidate& info, UnitId target, double weapon, double power,
        GroupStats& shooter, std::vector<GroupStats>& stats, double& damageOwner, double& damageScore)
    {
        ++shooter.shots;
        ++stats[info.group].targeted;
        if (random.between(1, 10000) > info.chance * 100) {
            return;
        }
        ++shooter.hits;
        auto& group = groups[info.group];
        auto& health = hp[target];
        auto& shield = shields[target];
        const auto crit = std::max(0.0, 1.0 - health / (group.health * 0.25));
        if (crit > 0 && random.between(1, 10000) <= crit * 10000) {
            health = 0.0;
        } else {
            const auto damage = weapon * info.factor * power;
            const auto toShield = std::min(shield, damage);
            shield -= toShield;
            const auto toStructure = std::min(health, std::max(0.0, damage - toShield));
            health = std::max(0.0, health - toStructure);
            const auto applied = toShield + toStructure;
            shooter.damage += applied;
            shooter.shieldDamage += toShield;
            shooter.structureDamage += toStructure;
            damageOwner += applied;
            damageScore += applied;
        }

        const auto offset = target - group.start;
        if (health <= 0) {
            ++stats[info.group].lost;
            group.wounded.set(offset, false);
            group.alive.set(offset, false);
        } else {
            group.wounded.set(offset, health < group.health || shield < group.shield);
        }
    }

    void playRound(unsigned round)
    {
        std::vector<UnitId> order;
        uint64_t participants = 0;
        for (const auto& group : groups) {
            if (group.arrival <= round) {
                participants += group.alive.total;
            }
        }
        order.reserve(participants);
        std::vector<std::vector<Candidate>> primary(groups.size()), secondary(groups.size());
        for (unsigned i = 0; i < groups.size(); ++i) {
            const auto& attacker = groups[i];
            if (attacker.arrival > round) {
                continue;
            }
            for (UnitId index = attacker.start; index < attacker.start + attacker.quantity; ++index) {
                if (hp[index] > 0) {
                    order.push_back(index);
                }
            }
            for (unsigned j = 0; j < groups.size(); ++j) {
                const auto& target = groups[j];
                if (target.arrival <= round && target.alive.total != 0 && hostile(attacker, target)) {
                    const auto factor = attacker.counters[j];
                    primary[i].push_back({j, factor, hitChance(attacker, target, 0, factor)});
                    if (target.category == attacker.secondCategory) {
                        secondary[i].push_back({j, factor, hitChance(attacker, target, attacker.secondPrecision, factor)});
                    }
                }
            }
        }
        // Fisher–Yates descendant, identique à shuffleArray de Randomizer.
        for (auto left = order.size(); left > 1; --left) {
            std::swap(order[left - 1], order[random.zeroTo(left - 1)]);
        }

        std::vector<GroupStats> stats(groups.size());
        std::vector<double> ownerDamage(ownerSize), scoreDamage(scoreSize + 1);
        for (const auto shooter : order) {
            if (hp[shooter] <= 0) {
                continue;
            }
            const auto attackerIndex = groupOf(shooter);
            const auto& attacker = groups[attackerIndex];
            if (attacker.attack <= 0 || std::none_of(primary[attackerIndex].begin(), primary[attackerIndex].end(),
                [&](const auto& candidate) { return groups[candidate.group].alive.total != 0; })) {
                continue;
            }

            const auto share = std::clamp((hp[shooter] + shields[shooter]) / (attacker.health + attacker.shield), 0.0, 1.0);
            const auto power = share * share;
            auto& shooterStats = stats[attackerIndex];
            auto& dealt = ownerDamage[attacker.owner];
            auto& score = scoreDamage[attacker.scoreOwner];
            const auto primaryShots = shotCount(attacker, attacker.shotsMin, attacker.shotsMax);
            UnitId target = noUnit;
            const Candidate* choice = nullptr;
            for (unsigned i = 0; i < primaryShots; ++i) {
                if (target == noUnit || hp[target] <= 0) {
                    choice = choose(primary[attackerIndex], target);
                    if (choice == nullptr) {
                        break;
                    }
                }
                fire(*choice, target, attacker.attack, power, shooterStats, stats, dealt, score);
            }

            if (attacker.secondAttack > 0 && attacker.secondMax > 0) {
                const auto secondaryShots = shotCount(attacker, attacker.secondMin, attacker.secondMax);
                for (unsigned i = 0; i < secondaryShots; ++i) {
                    choice = choose(secondary[attackerIndex], target);
                    if (choice == nullptr) {
                        break;
                    }
                    fire(*choice, target, attacker.secondAttack, power, shooterStats, stats, dealt, score);
                }
            }
        }

        for (auto& group : groups) {
            if (group.arrival > round || group.shield == 0) {
                continue;
            }
            const auto recovery = group.shield * recharge / 100;
            for (UnitId unit = group.start; unit < group.start + group.quantity; ++unit) {
                if (hp[unit] > 0 && shields[unit] > 0 && shields[unit] < group.shield) {
                    shields[unit] = std::min(group.shield, shields[unit] + recovery);
                    group.wounded.set(unit - group.start, hp[unit] < group.health || shields[unit] < group.shield);
                }
            }
        }

        std::cout << "R " << round << '\n';
        for (unsigned i = 0; i < stats.size(); ++i) {
            const auto& row = stats[i];
            if (row.shots != 0 || row.targeted != 0) {
                std::cout << "G " << i << ' ' << row.shots << ' ' << row.hits << ' ' << row.lost << ' '
                    << row.damage << ' ' << row.shieldDamage << ' ' << row.structureDamage << ' ' << row.targeted << '\n';
            }
        }
        for (unsigned i = 0; i < ownerDamage.size(); ++i) {
            if (ownerDamage[i] != 0) {
                std::cout << "D " << i << ' ' << ownerDamage[i] << '\n';
            }
        }
        for (unsigned i = 1; i < scoreDamage.size(); ++i) {
            if (scoreDamage[i] != 0) {
                std::cout << "M " << i << ' ' << scoreDamage[i] << '\n';
            }
        }
    }

    std::pair<std::string, int> classify(unsigned round) const
    {
        if (independent) {
            int camp = -1;
            bool defender = false;
            for (const auto& group : groups) {
                if (group.alive.total == 0) {
                    continue;
                }
                if (camp != -1 && camp != static_cast<int>(group.camp)) {
                    return {round == maxRounds ? "defender_wins" : "ongoing", -1};
                }
                camp = static_cast<int>(group.camp);
                defender = defender || group.role != 0;
            }
            return {camp == -1 ? "draw" : (defender ? "defender_wins" : "attacker_wins"), camp};
        }
        bool attackers = false;
        bool defenders = false;
        for (const auto& group : groups) {
            // Une flotte annoncée continue à compter pour la fin du combat.
            if (group.alive.total != 0) {
                if (group.role == 0) {
                    attackers = true;
                } else {
                    defenders = true;
                }
            }
        }
        if (!attackers) {
            return {defenders ? "defender_wins" : "draw", -1};
        }
        if (!defenders) {
            return {"attacker_wins", -1};
        }
        return {round == maxRounds ? "defender_wins" : "ongoing", -1};
    }
};
} // namespace

int main(int argc, char** argv)
{
    try {
        require(argc == 3);
        const auto parent = getppid();
        require(prctl(PR_SET_PDEATHSIG, 9) == 0 && getppid() == parent);
        // Limiter l'enfant, pas le worker PHP. Une allocation excessive doit
        // échouer ici plutôt que provoquer un arrêt du conteneur entier.
        rlimit limits{};
        require(getrlimit(RLIMIT_AS, &limits) == 0);
        limits.rlim_cur = std::min(limits.rlim_cur, nativeMemoryBudget);
        require(setrlimit(RLIMIT_AS, &limits) == 0);
        std::ios::sync_with_stdio(false);
        std::cin.tie(nullptr);
        std::cin.imbue(std::locale::classic());
        std::cout.imbue(std::locale::classic());
        Combat combat;
        if (std::strcmp(argv[1], "-") != 0) {
            combat.restoreCheckpoint(argv[1]);
        }
        combat.fight(argv[2]);
        return 0;
    } catch (const std::bad_alloc&) {
        return 2;
    } catch (const std::length_error&) {
        return 2;
    } catch (const std::exception& error) {
        std::cerr << error.what() << '\n';
        return 1;
    }
}
