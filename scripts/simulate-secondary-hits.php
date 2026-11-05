<?php
/** php scripts/simulate-secondary-hits.php [--csv] : calcul exact, sans recherche. */
require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatSimulator;

$catalog=new GameCatalog(dirname(__DIR__).'/config/game');$simulator=new CombatSimulator();
$matrix=$catalog->all('combat_modifiers')['modifiers'];
$attackers=array_filter($catalog->all('ships'),static fn(array $d):bool=>isset($d['secondary_attack']));
$targets=$catalog->all('ships');
$targetClass=static function(string $slug)use($catalog):string{
    foreach($catalog->all('combat_modifiers')['target_groups']??[] as $group=>$definition)if(in_array($slug,$definition['members'],true))return $group;
    return $slug;
};
$calculate=static function(array $attacker,array $target)use($simulator,$matrix):?float{
    if(($target['category']??null)!==($attacker['secondary_target_category']??null))return null;
    return $simulator->hitChance($attacker,$target,(int)($attacker['secondary_precision_modifier']??0),(float)($matrix[$attacker['slug']][$target['slug']]??1));
};
if(in_array('--csv',$argv,true)){
    fputcsv(STDOUT,['tireur','rang_tireur','cible','rang_cible','categorie_cible','classe_contre_cible','cible_admissible','bonus_secondaire','coefficient_contre','chance_toucher_pourcent'],';', '"', '');
    foreach($attackers as $base)foreach(GameCatalog::TIERS as $attackerTier)foreach($targets as $targetBase)foreach(GameCatalog::TIERS as $targetTier){
        $a=$catalog->withTier($base,$attackerTier);$t=$catalog->withTier($targetBase,$targetTier);$chance=$calculate($a,$t);
        fputcsv(STDOUT,[$base['slug'],$attackerTier,$t['slug'],$targetTier,$t['category'],$targetClass($t['slug']),$chance!==null?'oui':'non',$a['secondary_precision_modifier'],$matrix[$a['slug']][$t['slug']]??1,$chance===null?'':number_format($chance,2,'.','')],';','"','');
    }
    exit;
}
$format=static fn(float $n):string=>rtrim(rtrim(number_format($n,2,',',' '),'0'),',');
echo "# Chances de toucher des armes secondaires — 4 novembre 2026\n\n";
echo "Calcul exact avec les configurations du dépôt, sans recherches ni surcharge d’univers. Les tireurs et cibles sont au même rang. Le résultat est identique aux trois rangs lorsque leurs rangs sont égaux.\n\n";
echo "Bonus secondaire : **+30 points**. Chance de base = précision + suivi + bonus secondaire − esquive − 15 + 10 × écart de rang, bornée à 15–95 %. Le coefficient de contre multiplie cette chance, puis les bornes de 15–95 % sont réappliquées. Le même coefficient multiplie aussi les dégâts par impact.\n\n";
echo '| Type de vaisseau ciblé | Classe de contre | Classe de ciblage | '.implode(' | ',array_map(fn(array $d):string=>$catalog->combatLabel($d['slug']),$attackers))." |\n";
echo '|---|---|---|'.str_repeat('---:|',count($attackers))."\n";
foreach($targets as $base){
    $target=$catalog->withTier($base,'basic');$cells=[];
    foreach($attackers as $attacker){$chance=$calculate($catalog->withTier($attacker,'basic'),$target);$cells[]=$chance===null?'—':$format($chance).' %';}
    echo '| '.$catalog->combatLabel($base['slug']).' | '.$catalog->combatLabel($targetClass($base['slug'])).' | '.$base['category'].' | '.implode(' | ',$cells)." |\n";
}
echo "\n**— : cible non admissible, aucun tir secondaire.** Le filtre actuel des quatre armes secondaires est `light`. Le Chasseur lourd est classé `heavy` et reste exclu tant qu’une décision ne change pas sa classe.\n\n";
echo "La classe de contre Chasseur regroupe le Chasseur standard, le Chasseur furtif et l’Intercepteur, aux trois rangs. Le Chasseur lourd reste distinct. Un coefficient envers cette classe s’applique à chacun de ses membres ; il ne dépend pas du nom de la variante. Les anciens bonus regroupés sont consolidés à leur valeur la plus forte, sans cumul.\n\n";
echo "La variante CSV détaille les 504 confrontations : quatre tireurs × quatorze cibles × trois rangs de tireur × trois rangs de cible. Commande : `php scripts/simulate-secondary-hits.php --csv`.\n";
