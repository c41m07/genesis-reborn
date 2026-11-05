import { Controller } from '@hotwired/stimulus';

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
const missionColors = {
    explore: '#00d4ff', spy: '#a78bfa', colonize: '#2dd4a0', transport: '#34d399',
    attack: '#f43f5e', conquer: '#ffb040', defend: '#60a5fa', orbit: '#f472b6',
};
const starColors = ['#7190ff', '#9db9ff', '#d1dcff', '#fff1c5', '#ffe28c', '#ffbd70', '#ff775f'];
const planetPalettes = [
    { land: [61, 126, 150], sea: [22, 54, 104], cloud: [229, 238, 246], glow: '#67c9ff' },
    { land: [164, 116, 61], sea: [83, 56, 34], cloud: [239, 210, 161], glow: '#ffbe72' },
    { land: [81, 128, 74], sea: [26, 67, 103], cloud: [229, 238, 239], glow: '#72d6c5' },
    { land: [152, 105, 178], sea: [61, 46, 116], cloud: [221, 215, 247], glow: '#b8a4ff' },
    { land: [166, 183, 193], sea: [61, 112, 148], cloud: [241, 247, 252], glow: '#b7eaff' },
    { land: [177, 74, 54], sea: [70, 38, 42], cloud: [206, 143, 117], glow: '#ff9068' },
];

export default class extends Controller {
    static targets = [
        'scene', 'galaxyCanvas', 'systemCanvas', 'planetCanvas', 'location', 'coordinates',
        'searchInput', 'searchResults', 'searchClear', 'searchStatus', 'tooltip', 'systemBack', 'planetBack',
        'systemPanel', 'systemContent', 'planetPanel', 'planetContent', 'minimapWrap',
        'minimapCanvas', 'fleetLog', 'motionToggle', 'galaxySelect', 'mapHelp',
    ];

    static values = { map: Object, reducedMotion: Boolean };

    connect() {
        this.gameMap = this.mapValue;
        this.systems = this.gameMap.systems.filter((system) => Number(system.number) <= 80).map((system) => ({ ...system, planets: system.planets || [] }));
        this.systemById = new Map(this.systems.map((system) => [Number(system.id), system]));
        this.flights = (this.gameMap.flights || []).map((flight) => ({
            ...flight,
            originSystem: this.systemById.get(Number(flight.originSystemId)),
            destinationSystem: this.systemById.get(Number(flight.destinationSystemId)),
            trail: [],
        }));
        this.serverClockOffset = Date.parse(this.gameMap.serverNow || new Date().toISOString()) - Date.now();
        this.selectedSystem = null;
        this.selectedPlanet = null;
        this.mode = 'galaxy';
        this.drag = null;
        this.hoveredSystem = null;
        this.frameId = null;
        this.lastFrame = 0;
        this.elapsed = 0;
        this.lastClockPaint = 0;
        this.baseCamera = { x: 0, y: 0 };
        this.camera = { x: 0, y: 0 };
        this.targetZoom = 1;
        this.zoom = 1;
        this.baseZoom = 1;
        this.motionReduced = this.reducedMotionValue || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.random = this.seededRandom((Number(this.galaxySelectTarget.value) || 1) * 7919);
        this.projectSystems();
        this.makeDecorativeStars();
        this.bindEvents();
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.sceneTarget);
        this.visibilityHandler = () => {
            if (document.hidden) {
                this.stopLoop();
                if (this.timerInterval) window.clearInterval(this.timerInterval);
                this.timerInterval = null;
                return;
            }
            if (this.flights.length && !this.timerInterval) this.timerInterval = window.setInterval(() => this.updateFlightTimers(), 1000);
            this.updateFlightTimers();
            this.startLoop();
        };
        document.addEventListener('visibilitychange', this.visibilityHandler);
        this.resize();
        this.indexSearch();
        this.updateMotionButton();
        this.timerInterval = this.flights.length ? window.setInterval(() => this.updateFlightTimers(), 1000) : null;
        this.updateFlightTimers();
        this.startLoop();
    }

    disconnect() {
        this.stopLoop();
        if (this.timerInterval) window.clearInterval(this.timerInterval);
        this.resizeObserver?.disconnect();
        document.removeEventListener('visibilitychange', this.visibilityHandler);
        this.unbindEvents();
    }

    seededRandom(seed) {
        let value = (seed | 0) || 1;
        return () => {
            value = (value * 16807) % 2147483647;
            return (value - 1) / 2147483646;
        };
    }

    makeDecorativeStars() {
        const random = this.seededRandom((Number(this.galaxySelectTarget.value) || 1) * 104729);
        this.backgroundStars = Array.from({ length: 1050 }, () => ({
            x: random(), y: random(), size: random() * 1.65 + .4, phase: random() * Math.PI * 2,
            alpha: .27 + random() * .65,
        }));
        const minimapRandom = this.seededRandom((Number(this.galaxySelectTarget.value) || 1) * 7919 + 3511);
        this.minimapStars = Array.from({ length: 86 }, () => {
            const angle = minimapRandom() * Math.PI * 2;
            const radius = Math.sqrt(minimapRandom());
            return {
                x: Math.cos(angle) * radius,
                y: Math.sin(angle) * radius,
                size: .45 + minimapRandom() * .75,
                alpha: .22 + minimapRandom() * .5,
                color: minimapRandom() < .5 ? '#a7d9ff' : '#ffe1ae',
            };
        });
        this.dust = Array.from({ length: 5200 }, () => {
            const arm = Math.floor(random() * 8);
            const radial = Math.sqrt(random());
            const distance = 80 + radial * this.galaxyExtentX * .96;
            const angle = arm * Math.PI / 4 + radial * .82 + (random() - .5) * (.13 + radial * .32);
            return {
                x: Math.cos(angle) * distance,
                y: Math.sin(angle) * distance,
                size: .45 + random() * 2.5,
                color: random() < .28 ? [119, 174, 236] : random() < .52 ? [246, 194, 132] : random() < .78 ? [184, 160, 239] : [129, 218, 224],
                alpha: .12 + random() * .42, phase: random() * Math.PI * 2,
            };
        });
        this.nebulae = Array.from({ length: 58 }, () => {
            const arm = Math.floor(random() * 8);
            const radial = .12 + random() * .82;
            const distance = 140 + radial * this.galaxyExtentX * .9;
            const angle = arm * Math.PI / 4 + radial * .82 + (random() - .5) * .42;
            return {
                x: Math.cos(angle) * distance, y: Math.sin(angle) * distance,
                radius: 180 + random() * 420, hue: random() < .52 ? [55, 130, 222] : [163, 93, 209],
                alpha: .055 + random() * .09, phase: random() * Math.PI * 2,
            };
        });
    }

    galaxyArmPoint(arm, progress) {
        const t = clamp(progress, 0, 1);
        const radius = .07 + t * .828;
        const angle = arm * Math.PI / 4 + t * .82;
        return {
            wx: Math.cos(angle) * radius * this.galaxyExtentX,
            wy: Math.sin(angle) * radius * this.galaxyExtentY,
        };
    }

    galaxySystemPosition(branch, slot) {
        return this.galaxyArmPoint(branch, slot / 9);
    }

    projectSystems() {
        if (!this.systems.length) return;
        // Vue du dessus : huit bras circulaires avec dix emplacements chacun.
        this.layoutScale = clamp(Math.sqrt(this.systems.length / 24), .7, 1.8);
        this.galaxyExtentX = 2100 * this.layoutScale;
        this.galaxyExtentY = 2100 * this.layoutScale;
        const knownNumbers = new Set(this.systems.map((system) => Number(system.number)));
        this.decorativeSystems = Array.from({ length: 80 }, (_, index) => {
            const number = index + 1;
            if (knownNumbers.has(number)) return null;
            const branch = Math.floor(index / 10);
            const slot = index % 10;
            return {
                number,
                ...this.galaxySystemPosition(branch, slot),
            };
        }).filter(Boolean);
        this.systems.forEach((system) => {
            const number = Number(system.number);
            const index = Math.max(0, number - 1);
            const branch = Math.floor(index / 10);
            const slot = index % 10;
            Object.assign(system, this.galaxySystemPosition(branch, slot));
            const random = this.seededRandom(Number(system.id) * 7919 + 47);
            system.starColor = starColors[Math.floor(random() * starColors.length)];
            system.starRadius = .65 + random() * 1.6;
            system.pulse = random() * Math.PI * 2;
            system.planets.forEach((planet) => {
                const visualRandom = this.seededRandom(Number(planet.id) * 92821 + 13);
                planet.visual = {
                    palette: planetPalettes[Math.floor(visualRandom() * planetPalettes.length)],
                    kind: visualRandom() < .28 ? 'gas' : (visualRandom() < .46 ? 'ice' : 'terrestrial'),
                    radius: 4.3 + visualRandom() * 3.8,
                    orbitRadius: 84 + (Number(planet.position) || 1) * 42,
                    angle: visualRandom() * Math.PI * 2,
                    speed: .055 / Math.sqrt(Number(planet.position) || 1),
                    ring: visualRandom() > .78,
                    moonAngle: visualRandom() * Math.PI * 2,
                    moonDistance: 11 + visualRandom() * 8,
                    moonSize: 1.2 + visualRandom() * 1.5,
                    seed: Number(planet.id) * 113 + 19,
                    rotation: 0,
                    texture: null,
                    sphereCanvas: null,
                    sphereContext: null,
                    spherePixels: null,
                    sphereSize: 256,
                };
            });
        });
        // La caméra et la mini-carte partagent le centre géométrique de l’ellipse.
        this.systemBounds = { minX: -this.galaxyExtentX, maxX: this.galaxyExtentX, minY: -this.galaxyExtentY, maxY: this.galaxyExtentY };
        this.flights.forEach((flight) => {
            flight.originSystem = this.systemById.get(Number(flight.originSystemId));
            flight.destinationSystem = this.systemById.get(Number(flight.destinationSystemId));
            flight.trail = [];
        });
    }

    getBounds(points) {
        if (!points.length) return { minX: -2100, maxX: 2100, minY: -780, maxY: 780 };
        return {
            minX: Math.min(...points.map((point) => point.x)),
            maxX: Math.max(...points.map((point) => point.x)),
            minY: Math.min(...points.map((point) => point.y)),
            maxY: Math.max(...points.map((point) => point.y)),
        };
    }

    bindEvents() {
        this.pointerDownHandler = (event) => this.pointerDown(event);
        this.pointerMoveHandler = (event) => this.pointerMove(event);
        this.pointerUpHandler = (event) => this.pointerUp(event);
        this.wheelHandler = (event) => {
            if (event.target.closest('button, input, select, textarea, a, .gv2-hud, .gv2-map-controls, .gv2-minimap, .gv2-flight-log, .gv2-system-panel, .gv2-planet-panel')) return;
            this.wheel(event);
        };
        this.systemClickHandler = (event) => this.systemCanvasClick(event);
        this.systemDoubleClickHandler = (event) => this.systemCanvasDoubleClick(event);
        this.minimapPointerHandler = (event) => this.minimapPointer(event);
        this.searchOutsideHandler = (event) => {
            if (!event.target.closest('.gv2-search')) this.hideSearchResults();
        };

        this.sceneTarget.addEventListener('pointerdown', this.pointerDownHandler);
        this.sceneTarget.addEventListener('pointermove', this.pointerMoveHandler);
        this.sceneTarget.addEventListener('pointerup', this.pointerUpHandler);
        this.sceneTarget.addEventListener('pointercancel', this.pointerUpHandler);
        this.sceneTarget.addEventListener('wheel', this.wheelHandler, { passive: false });
        this.systemCanvasTarget.addEventListener('click', this.systemClickHandler);
        this.systemCanvasTarget.addEventListener('dblclick', this.systemDoubleClickHandler);

        this.minimapCanvasTarget.addEventListener('pointerdown', this.minimapPointerHandler);
        this.searchOutsideHandler = this.searchOutsideHandler.bind(this);
        document.addEventListener('pointerdown', this.searchOutsideHandler);
        this.motionPreferenceChange = (event) => {
            this.motionReduced = event.matches || this.reducedMotionValue;
            this.updateMotionButton();
            this.motionReduced ? this.stopLoop() : this.startLoop();
            this.drawFrame(0);
        };
        this.motionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.motionMedia.addEventListener?.('change', this.motionPreferenceChange);
    }

    unbindEvents() {
        this.sceneTarget.removeEventListener('pointerdown', this.pointerDownHandler);
        this.sceneTarget.removeEventListener('pointermove', this.pointerMoveHandler);
        this.sceneTarget.removeEventListener('pointerup', this.pointerUpHandler);
        this.sceneTarget.removeEventListener('pointercancel', this.pointerUpHandler);
        this.sceneTarget.removeEventListener('wheel', this.wheelHandler);
        this.systemCanvasTarget.removeEventListener('click', this.systemClickHandler);
        this.systemCanvasTarget.removeEventListener('dblclick', this.systemDoubleClickHandler);

        this.minimapCanvasTarget.removeEventListener('pointerdown', this.minimapPointerHandler);
        document.removeEventListener('pointerdown', this.searchOutsideHandler);
        this.motionMedia?.removeEventListener?.('change', this.motionPreferenceChange);
    }

    resize() {
        const rect = this.sceneTarget.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        this.width = rect.width;
        this.height = rect.height;
        this.dpr = Math.min(window.devicePixelRatio || 1, 1.75);
        for (const canvas of [this.galaxyCanvasTarget, this.systemCanvasTarget, this.planetCanvasTarget]) {
            canvas.width = Math.round(this.width * this.dpr);
            canvas.height = Math.round(this.height * this.dpr);
            canvas.style.width = this.width + 'px';
            canvas.style.height = this.height + 'px';
            canvas.getContext('2d').setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
        }
        const miniRect = this.minimapCanvasTarget.getBoundingClientRect();
        this.minimapCanvasTarget.width = Math.max(1, Math.round(miniRect.width * this.dpr));
        this.minimapCanvasTarget.height = Math.max(1, Math.round(miniRect.height * this.dpr));
        this.minimapContext = this.minimapCanvasTarget.getContext('2d');
        this.minimapContext.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
        this.fitGalaxy();
        this.drawFrame(0);
    }

    fitGalaxy() {
        const extentX = this.galaxyExtentX || 2100;
        const extentY = this.galaxyExtentY || 780;
        const bounds = this.systemBounds || { minX: -extentX, maxX: extentX, minY: -extentY, maxY: extentY };
        const paddingX = Math.max(180, extentX * .1);
        const paddingY = Math.max(150, extentY * .16);
        const spanX = Math.max(extentX * 2 + paddingX * 2, bounds.maxX - bounds.minX + paddingX * 2);
        const spanY = Math.max(extentY * 2 + paddingY * 2, bounds.maxY - bounds.minY + paddingY * 2);
        this.baseCamera = {
            x: (bounds.minX + bounds.maxX) / 2,
            y: (bounds.minY + bounds.maxY) / 2,
        };
        this.baseZoom = clamp(Math.min((this.width - 100) / spanX, (this.height - 100) / spanY), .045, .8);
        if (!this.hasUserCamera) {
            this.camera = { ...this.baseCamera };
            this.zoom = this.targetZoom = this.baseZoom;
        }
    }

    startLoop() {
        if (this.motionReduced || document.hidden || this.frameId !== null) {
            this.drawFrame(0);
            return;
        }
        this.lastFrame = performance.now();
        this.frameId = requestAnimationFrame((time) => this.frame(time));
    }

    stopLoop() {
        if (this.frameId !== null) cancelAnimationFrame(this.frameId);
        this.frameId = null;
    }

    frame(time) {
        this.frameId = null;
        if (document.hidden || !this.element.isConnected) return;
        const dt = Math.min(.05, Math.max(0, (time - this.lastFrame) / 1000));
        this.lastFrame = time;
        this.elapsed += dt;
        this.zoom += (this.targetZoom - this.zoom) * Math.min(1, dt * 8);
        if (this.cameraAnimation) this.stepCameraAnimation(time);
        this.drawFrame(dt);
        if (!this.motionReduced) this.frameId = requestAnimationFrame((next) => this.frame(next));
    }

    drawFrame(dt) {
        if (!this.width || !this.height) return;
        if (this.mode === 'galaxy' || this.mode === 'transition-to-system' || this.mode === 'transition-to-galaxy') this.drawGalaxy(dt);
        if (this.mode === 'system' || this.mode === 'transition-to-planet' || this.mode === 'transition-to-system-from-planet') this.drawSystem(dt);
        if (this.mode === 'planet') this.drawPlanet(dt);
        if (this.mode === 'galaxy') this.drawMinimap();
    }

    galaxyPoint(system) {
        const worldX = Number.isFinite(system.wx) ? system.wx : system.x;
        const worldY = Number.isFinite(system.wy) ? system.wy : system.y;
        return {
            x: this.width / 2 + (worldX - this.camera.x) * this.zoom,
            y: this.height / 2 + (worldY - this.camera.y) * this.zoom,
        };
    }

    drawGalaxy(dt) {
        const ctx = this.galaxyCanvasTarget.getContext('2d');
        ctx.clearRect(0, 0, this.width, this.height);
        this.drawBackdrop(ctx);
        for (const star of this.backgroundStars) {
            const alpha = star.alpha * (.58 + .42 * Math.sin(this.elapsed * .75 + star.phase));
            ctx.globalAlpha = alpha;
            ctx.fillStyle = '#c9d8ef';
            ctx.fillRect(star.x * this.width, star.y * this.height, star.size, star.size);
        }
        ctx.globalAlpha = 1;

        const center = this.galaxyPoint({ wx: 0, wy: 0 });
        const major = (Math.max(this.galaxyExtentX, this.galaxyExtentY) + 100) * this.zoom;
        const minor = major;
        const ellipseGradient = ctx.createRadialGradient(center.x, center.y, major * .04, center.x, center.y, major);
        ellipseGradient.addColorStop(0, 'rgba(255,220,168,.35)');
        ellipseGradient.addColorStop(.2, 'rgba(113,128,199,.27)');
        ellipseGradient.addColorStop(.52, 'rgba(57,111,182,.145)');
        ellipseGradient.addColorStop(1, 'rgba(27,46,95,0)');
        ctx.save();
        ctx.translate(center.x, center.y);
        ctx.scale(1, minor / major);
        ctx.fillStyle = ellipseGradient;
        ctx.beginPath();
        ctx.arc(0, 0, major, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
        this.drawGalaxyCore(ctx, center, major);

        // Repères orbitaux légers autour du noyau de la galaxie.
        ctx.save();
        ctx.translate(center.x, center.y);
        ctx.scale(1, minor / major);
        ctx.setLineDash([7, 15]);
        for (const ratio of [.28, .48, .68, .88]) {
            ctx.beginPath();
            ctx.arc(0, 0, major * ratio, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(113,184,236,' + (ratio > .7 ? '.105' : '.065') + ')';
            ctx.lineWidth = Math.max(.7, 1 / this.zoom);
            ctx.stroke();
        }
        ctx.setLineDash([]);
        ctx.restore();
        this.drawSpiralArms(ctx);

        for (const nebula of this.nebulae) {
            const point = this.galaxyPoint(nebula);
            const radius = nebula.radius * this.zoom;
            if (point.x + radius < 0 || point.x - radius > this.width || point.y + radius < 0 || point.y - radius > this.height) continue;
            const pulse = .84 + .16 * Math.sin(this.elapsed * .22 + nebula.phase);
            const glow = ctx.createRadialGradient(point.x, point.y, radius * .04, point.x, point.y, radius);
            glow.addColorStop(0, 'rgba(' + nebula.hue.join(',') + ',' + (nebula.alpha * pulse) + ')');
            glow.addColorStop(1, 'rgba(' + nebula.hue.join(',') + ',0)');
            ctx.fillStyle = glow;
            ctx.fillRect(point.x - radius, point.y - radius, radius * 2, radius * 2);
        }

        for (const dust of this.dust) {
            const point = this.galaxyPoint(dust);
            const size = Math.max(.35, dust.size * this.zoom);
            if (point.x < -2 || point.x > this.width + 2 || point.y < -2 || point.y > this.height + 2) continue;
            ctx.globalAlpha = dust.alpha * (.76 + .24 * Math.sin(this.elapsed * .7 + dust.phase));
            ctx.fillStyle = 'rgb(' + dust.color.join(',') + ')';
            ctx.fillRect(point.x, point.y, size, size);
        }
        ctx.globalAlpha = 1;

        const showLabels = this.systems.length <= 48 || this.zoom > Math.max(.08, this.baseZoom * .72);
        for (const placeholder of this.decorativeSystems || []) {
            const point = this.galaxyPoint(placeholder);
            if (point.x < -34 || point.x > this.width + 34 || point.y < -34 || point.y > this.height + 34) continue;
            ctx.save();
            ctx.globalAlpha = .38;
            const halo = ctx.createRadialGradient(point.x, point.y, 0, point.x, point.y, 18);
            halo.addColorStop(0, 'rgba(155,184,213,.62)');
            halo.addColorStop(1, 'rgba(155,184,213,0)');
            ctx.fillStyle = halo;
            ctx.fillRect(point.x - 18, point.y - 18, 36, 36);
            ctx.beginPath();
            ctx.arc(point.x, point.y, 4.1, 0, Math.PI * 2);
            ctx.fillStyle = '#9fb5cc';
            ctx.shadowColor = '#9fb5cc';
            ctx.shadowBlur = 7;
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.beginPath();
            ctx.arc(point.x, point.y, 7, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(190,211,230,.78)';
            ctx.lineWidth = 1.2;
            ctx.stroke();
            if (showLabels) {
                const label = this.gameMap.galaxy + ':' + placeholder.number;
                ctx.font = '9px "JetBrains Mono", monospace';
                ctx.textAlign = 'center';
                ctx.lineWidth = 3;
                ctx.strokeStyle = 'rgba(3,8,15,.96)';
                ctx.strokeText(label, point.x, point.y + 19);
                ctx.fillStyle = '#a9bfd5';
                ctx.fillText(label, point.x, point.y + 19);
            }
            ctx.restore();
        }
        for (const system of this.systems) {
            const point = this.galaxyPoint(system);
            if (point.x < -32 || point.x > this.width + 32 || point.y < -32 || point.y > this.height + 32) continue;
            const pulse = 1 + .09 * Math.sin(this.elapsed * .9 + system.pulse);
            const hasOwnedPlanet = system.planets.some((planet) => planet.ownedByCurrentPlayer);
            const markerColor = hasOwnedPlanet ? '#49e0ad' : (system.known ? system.starColor : '#a4bdd7');
            const isHovered = system === this.hoveredSystem;
            const radius = (isHovered ? 4.2 : (hasOwnedPlanet ? 3.8 : (system.known ? 3.4 : 3.1))) + system.starRadius * .42;
            {
                const haloRadius = isHovered ? 38 : (hasOwnedPlanet ? 31 : (system.known ? 25 : 21));
                const halo = ctx.createRadialGradient(point.x, point.y, 0, point.x, point.y, haloRadius);
                const haloColor = hasOwnedPlanet ? '45,212,160' : (system.known ? '0,212,255' : '120,157,196');
                const haloAlpha = isHovered ? '.48' : (hasOwnedPlanet ? '.42' : (system.known ? '.32' : '.25'));
                halo.addColorStop(0, 'rgba(' + haloColor + ',' + haloAlpha + ')');
                halo.addColorStop(1, 'rgba(' + haloColor + ',0)');
                ctx.fillStyle = halo;
                ctx.fillRect(point.x - haloRadius, point.y - haloRadius, haloRadius * 2, haloRadius * 2);
            }
            ctx.beginPath();
            ctx.arc(point.x, point.y, Math.max(1.1, radius * pulse), 0, Math.PI * 2);
            ctx.fillStyle = markerColor;
            ctx.shadowColor = markerColor;
            ctx.shadowBlur = isHovered ? 20 : (hasOwnedPlanet ? 16 : (system.known ? 13 : 10));
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.beginPath();
            ctx.arc(point.x, point.y, radius + (isHovered ? 8 : 5), 0, Math.PI * 2);
            ctx.strokeStyle = isHovered ? 'rgba(255,255,255,.9)' : (hasOwnedPlanet ? 'rgba(73,224,173,.76)' : 'rgba(157,202,239,.58)');
            ctx.lineWidth = isHovered ? 1.8 : 1.2;
            ctx.stroke();
            if (showLabels || isHovered) {
                const label = system.galaxy + ':' + system.number;
                ctx.font = isHovered || this.systems.length <= 48 ? '600 11px "JetBrains Mono", monospace' : '10px "JetBrains Mono", monospace';
                ctx.textAlign = 'center';
                ctx.lineWidth = 3.5;
                ctx.lineJoin = 'round';
                ctx.strokeStyle = 'rgba(3,8,15,.96)';
                ctx.strokeText(label, point.x, point.y + 22);
                ctx.fillStyle = isHovered || hasOwnedPlanet ? '#e4fff4' : '#d5e8fa';
                ctx.fillText(label, point.x, point.y + 22);
            }
            system.sx = point.x;
            system.sy = point.y;
        }

        this.drawFlights(ctx);
        if (this.hoveredSystem) this.drawSelection(ctx, this.hoveredSystem, '#00d4ff');
        if (this.selectedSystem && this.mode !== 'galaxy') this.drawSelection(ctx, this.selectedSystem, '#ffb040');
    }

    drawSpiralArms(ctx) {
        ctx.save();
        ctx.lineCap = 'round';
        for (let branch = 0; branch < 8; branch += 1) {
            ctx.beginPath();
            for (let step = 0; step <= 64; step += 1) {
                const position = this.galaxyArmPoint(branch, step / 64);
                const point = this.galaxyPoint(position);
                if (step === 0) ctx.moveTo(point.x, point.y);
                else ctx.lineTo(point.x, point.y);
            }

            const tint = branch % 2 === 0 ? '88,164,255' : '121,133,255';
            ctx.strokeStyle = 'rgba(' + tint + ',.055)';
            ctx.lineWidth = Math.max(3, 92 * this.zoom);
            ctx.shadowColor = 'rgba(' + tint + ',.28)';
            ctx.shadowBlur = Math.max(5, 36 * this.zoom);
            ctx.stroke();

            ctx.strokeStyle = 'rgba(190,218,255,.11)';
            ctx.lineWidth = Math.max(1, 11 * this.zoom);
            ctx.shadowBlur = 0;
            ctx.stroke();
        }
        ctx.restore();
    }

    drawGalaxyCore(ctx, center, galaxyRadius) {
        const radius = Math.max(14, galaxyRadius * .055);
        const core = ctx.createRadialGradient(center.x, center.y, 0, center.x, center.y, radius * 3.8);
        core.addColorStop(0, 'rgba(255,248,226,.82)');
        core.addColorStop(.12, 'rgba(255,218,157,.58)');
        core.addColorStop(.38, 'rgba(255,172,112,.2)');
        core.addColorStop(1, 'rgba(255,150,112,0)');
        ctx.fillStyle = core;
        ctx.fillRect(center.x - radius * 3.8, center.y - radius * 3.8, radius * 7.6, radius * 7.6);

        const nucleus = ctx.createRadialGradient(center.x - radius * .12, center.y - radius * .16, 0, center.x, center.y, radius);
        nucleus.addColorStop(0, 'rgba(255,255,244,.96)');
        nucleus.addColorStop(.28, 'rgba(255,231,184,.82)');
        nucleus.addColorStop(.7, 'rgba(255,191,132,.24)');
        nucleus.addColorStop(1, 'rgba(255,188,132,0)');
        ctx.fillStyle = nucleus;
        ctx.beginPath();
        ctx.arc(center.x, center.y, radius, 0, Math.PI * 2);
        ctx.fill();
    }

    drawSystemBackdrop(ctx, system) {
        this.drawBackdrop(ctx);
        this.drawLocalStars(ctx);
        const x = Math.min(this.width * .43, this.width - 210);
        const y = this.height * .53;
        const glowRadius = Math.max(this.width, this.height) * .62;
        const starColor = system?.starColor || '#ffe28c';
        const starRgb = starColor.match(/[a-f0-9]{2}/gi)?.map((part) => parseInt(part, 16)) || [255, 205, 130];
        const glow = ctx.createRadialGradient(x, y, 0, x, y, glowRadius);
        glow.addColorStop(0, 'rgba(' + starRgb.join(',') + ',.14)');
        glow.addColorStop(.24, 'rgba(110,147,220,.055)');
        glow.addColorStop(.66, 'rgba(77,104,170,.018)');
        glow.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = glow;
        ctx.fillRect(x - glowRadius, y - glowRadius, glowRadius * 2, glowRadius * 2);

        for (let band = 0; band < 3; band += 1) {
            ctx.beginPath();
            ctx.ellipse(x, y, 160 + band * 116, 52 + band * 42, -.08, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(156,191,232,' + (.035 - band * .008) + ')';
            ctx.lineWidth = 1;
            ctx.stroke();
        }
        if (system) this.drawLocalDust(ctx, system);
    }

    drawPlanetBackdrop(ctx, planet) {
        this.drawBackdrop(ctx);
        this.drawLocalStars(ctx);
        const x = this.width * .59, y = this.height * .53;
        const radius = Math.max(this.width, this.height) * .56;
        const hue = planet?.visual?.palette?.glow || '#7eb9e7';
        const color = hue.match(/[a-f0-9]{2}/gi)?.map((part) => parseInt(part, 16)) || [126, 185, 231];
        const haze = ctx.createRadialGradient(x, y, 0, x, y, radius);
        haze.addColorStop(0, 'rgba(' + color.join(',') + ',.13)');
        haze.addColorStop(.42, 'rgba(' + color.join(',') + ',.045)');
        haze.addColorStop(1, 'rgba(' + color.join(',') + ',0)');
        ctx.fillStyle = haze;
        ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);

        for (let i = 0; i < 5; i += 1) {
            const starX = ((i * 173 + 61) % 997) / 997 * this.width;
            const starY = ((i * 317 + 89) % 991) / 991 * this.height;
            ctx.fillStyle = 'rgba(218,235,255,' + (.24 + (i % 3) * .12) + ')';
            ctx.fillRect(starX, starY, 1.2, 1.2);
        }
    }

    drawLocalDust(ctx, system) {
        const seed = this.seededRandom(Number(system.id) * 3719 + 211);
        ctx.save();
        for (let i = 0; i < 180; i += 1) {
            const angle = seed() * Math.PI * 2;
            const distance = 80 + seed() * Math.max(this.width, this.height) * .43;
            const x = this.width * .43 + Math.cos(angle) * distance;
            const y = this.height * .53 + Math.sin(angle) * distance * .28;
            ctx.globalAlpha = .08 + seed() * .22;
            ctx.fillStyle = seed() < .45 ? '#9fcdf2' : '#f1c896';
            ctx.fillRect(x, y, .6 + seed() * 1.2, .6 + seed() * 1.2);
        }
        ctx.restore();
        ctx.globalAlpha = 1;
    }

    drawBackdrop(ctx) {
        const x = this.width * .48, y = this.height * .47;
        const radius = Math.max(this.width, this.height) * .86;
        const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
        gradient.addColorStop(0, '#10253a');
        gradient.addColorStop(.34, '#0a1727');
        gradient.addColorStop(.72, '#07111e');
        gradient.addColorStop(1, '#050910');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, this.width, this.height);
    }

    drawSelection(ctx, system, color) {
        const point = this.galaxyPoint(system);
        const r = 16 + 3 * Math.sin(this.elapsed * 1.5);
        ctx.save();
        ctx.strokeStyle = color + 'a0';
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 4]);
        ctx.beginPath();
        ctx.arc(point.x, point.y, r, 0, Math.PI * 2);
        ctx.stroke();
        ctx.setLineDash([]);
        for (const [dx, dy] of [[-1, -1], [1, -1], [1, 1], [-1, 1]]) {
            const x = point.x + dx * 22, y = point.y + dy * 22, leg = 6;
            ctx.beginPath();
            ctx.moveTo(x, y - dy * leg);
            ctx.lineTo(x, y);
            ctx.lineTo(x - dx * leg, y);
            ctx.stroke();
        }
        ctx.restore();
    }

    galaxyEdgePoint(reference, seed, incoming) {
        const extentX = this.galaxyExtentX || 2100;
        const extentY = this.galaxyExtentY || 780;
        const referenceAngle = reference ? Math.atan2(reference.wy / extentY, reference.wx / extentX) : (Number(seed) % 16) * Math.PI / 8;
        const angle = referenceAngle + (incoming ? Math.PI : 0);
        return { wx: Math.cos(angle) * extentX * 1.06, wy: Math.sin(angle) * extentY * 1.06 };
    }

    drawFlights(ctx) {
        const now = Date.now() + this.serverClockOffset;
        for (const flight of this.flights) {
            let origin = flight.originSystem;
            let destination = flight.destinationSystem;
            const originInGalaxy = flight.originGalaxy === this.gameMap.galaxy;
            const destinationInGalaxy = flight.destinationGalaxy === this.gameMap.galaxy;
            if (!origin && !originInGalaxy && destination) origin = this.galaxyEdgePoint(destination, flight.id, true);
            if (!destination && !destinationInGalaxy && origin) destination = this.galaxyEdgePoint(origin, flight.id, false);
            if (!origin || !destination || (!originInGalaxy && !destinationInGalaxy)) continue;
            if (['attacking', 'defending', 'orbiting', 'orbit_pending'].includes(flight.status)) {
                const point = this.galaxyPoint(destination);
                const color = missionColors[flight.mission] || '#00d4ff';
                ctx.save();
                ctx.strokeStyle = color;
                ctx.globalAlpha = .38 + .24 * Math.sin(this.elapsed * 2.4 + Number(flight.id));
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.arc(point.x, point.y, 7 + 3 * Math.sin(this.elapsed * 2.4), 0, Math.PI * 2);
                ctx.stroke();
                ctx.restore();
                continue;
            }
            if (!['traveling', 'returning'].includes(flight.status)) continue;
            const departure = Date.parse(flight.departureAt || '');
            const arrival = Date.parse(flight.arrivalAt || '');
            if (!Number.isFinite(departure) || !Number.isFinite(arrival) || arrival <= departure) continue;
            let progress = clamp((now - departure) / (arrival - departure), 0, 1);
            let from = origin, to = destination;
            if (flight.status === 'returning') {
                progress = 1 - progress;
                from = destination;
                to = origin;
            }
            const a = this.galaxyPoint(from), b = this.galaxyPoint(to);
            const bend = Math.min(85, Math.max(14, Math.hypot(b.x - a.x, b.y - a.y) * .16));
            const dx = b.x - a.x, dy = b.y - a.y, length = Math.max(1, Math.hypot(dx, dy));
            const cx = (a.x + b.x) / 2 - dy / length * bend;
            const cy = (a.y + b.y) / 2 + dx / length * bend;
            const pointOnRoute = (routeProgress) => {
                const inverse = 1 - routeProgress;
                return {
                    x: inverse * inverse * a.x + 2 * inverse * routeProgress * cx + routeProgress * routeProgress * b.x,
                    y: inverse * inverse * a.y + 2 * inverse * routeProgress * cy + routeProgress * routeProgress * b.y,
                };
            };
            const { x, y } = pointOnRoute(progress);
            flight.trail.push(progress);
            if (flight.trail.length > 12) flight.trail.shift();
            const color = missionColors[flight.mission] || '#00d4ff';
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.quadraticCurveTo(cx, cy, b.x, b.y);
            ctx.strokeStyle = color + '27';
            ctx.setLineDash([4, 7]);
            ctx.stroke();
            ctx.setLineDash([]);
            for (let i = 1; i < flight.trail.length; i++) {
                const previous = pointOnRoute(flight.trail[i - 1]);
                const current = pointOnRoute(flight.trail[i]);
                ctx.globalAlpha = i / flight.trail.length * .82;
                ctx.strokeStyle = color;
                ctx.lineWidth = .6 + i / flight.trail.length * 1.3;
                ctx.beginPath();
                ctx.moveTo(previous.x, previous.y);
                ctx.lineTo(current.x, current.y);
                ctx.stroke();
            }
            ctx.globalAlpha = 1;
            ctx.translate(x, y);
            ctx.rotate(Math.atan2(dy, dx));
            ctx.shadowColor = color;
            ctx.shadowBlur = 10;
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.moveTo(8, 0);
            ctx.lineTo(-5, -3.2);
            ctx.lineTo(-2, 0);
            ctx.lineTo(-5, 3.2);
            ctx.closePath();
            ctx.fill();
            ctx.restore();
        }
    }

    drawMinimap() {
        if (!this.systems.length || !this.minimapContext) return;
        const rect = this.minimapCanvasTarget.getBoundingClientRect();
        const width = rect.width, height = rect.height, ctx = this.minimapContext;
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = 'rgba(3,8,16,.98)';
        ctx.fillRect(0, 0, width, height);
        const bounds = this.systemBounds;
        const spanX = Math.max(1, bounds.maxX - bounds.minX), spanY = Math.max(1, bounds.maxY - bounds.minY);
        const scale = Math.min((width - 16) / spanX, (height - 16) / spanY);
        const mapX = (x) => width / 2 + (x - (bounds.minX + bounds.maxX) / 2) * scale;
        const mapY = (y) => height / 2 + (y - (bounds.minY + bounds.maxY) / 2) * scale;
        const centerX = width / 2, centerY = height / 2;
        const radiusY = Math.min(width * .46, height * .39, spanX * scale / 2, spanY * scale / 2);
        const radiusX = radiusY;
        const galaxyGradient = ctx.createRadialGradient(centerX, centerY, 1, centerX, centerY, Math.max(radiusX, radiusY));
        galaxyGradient.addColorStop(0, 'rgba(255,216,151,.46)');
        galaxyGradient.addColorStop(.16, 'rgba(166,151,224,.32)');
        galaxyGradient.addColorStop(.5, 'rgba(41,125,205,.2)');
        galaxyGradient.addColorStop(1, 'rgba(20,67,119,0)');
        ctx.beginPath();
        ctx.ellipse(centerX, centerY, radiusX, radiusY, 0, 0, Math.PI * 2);
        ctx.fillStyle = galaxyGradient;
        ctx.fill();
        ctx.strokeStyle = 'rgba(97,191,245,.52)';
        ctx.lineWidth = 1;
        ctx.stroke();
        for (let arm = 0; arm < 8; arm++) {
            ctx.beginPath();
            for (let step = 0; step <= 90; step++) {
                const progress = step / 90;
                const angle = arm * Math.PI / 4 + progress * .82;
                const x = centerX + Math.cos(angle) * progress * radiusX * .94;
                const y = centerY + Math.sin(angle) * progress * radiusY * .9;
                if (step === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            }
            ctx.strokeStyle = arm % 2 ? 'rgba(103,151,239,.52)' : 'rgba(108,222,232,.48)';
            ctx.lineWidth = 1.8;
            ctx.shadowColor = ctx.strokeStyle;
            ctx.shadowBlur = 4;
            ctx.stroke();
            ctx.shadowBlur = 0;
        }
        for (const star of this.minimapStars || []) {
            ctx.fillStyle = star.color;
            ctx.globalAlpha = star.alpha;
            ctx.fillRect(centerX + star.x * radiusX * .9, centerY + star.y * radiusY * .86, star.size, star.size);
        }
        ctx.globalAlpha = 1;
        for (const placeholder of this.decorativeSystems || []) {
            const x = mapX(placeholder.wx), y = mapY(placeholder.wy);
            ctx.beginPath();
            ctx.arc(x, y, 2.1, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(151,184,214,.42)';
            ctx.fill();
            ctx.beginPath();
            ctx.arc(x, y, 3.1, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(190,211,230,.38)';
            ctx.lineWidth = .7;
            ctx.stroke();
        }
        for (const system of this.systems) {
            const x = mapX(system.wx), y = mapY(system.wy);
            const radius = system.known ? 2.8 : 2.2;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.fillStyle = system.known ? system.starColor : '#91a9c4';
            ctx.shadowColor = ctx.fillStyle;
            ctx.shadowBlur = 5;
            ctx.fill();
            ctx.shadowBlur = 0;
        }
        const viewWidth = this.width / this.zoom, viewHeight = this.height / this.zoom;
        const viewCenterX = mapX(this.camera.x), viewCenterY = mapY(this.camera.y);
        ctx.strokeStyle = 'rgba(0,212,255,.7)';
        ctx.lineWidth = 1;
        ctx.strokeRect(viewCenterX - viewWidth * scale / 2, viewCenterY - viewHeight * scale / 2, viewWidth * scale, viewHeight * scale);
    }

    pointerDown(event) {
        if (event.button !== undefined && event.button !== 0) return;
        if (event.target.closest('button, input, select, textarea, a, .gv2-hud, .gv2-map-controls, .gv2-minimap, .gv2-flight-log, .gv2-system-panel, .gv2-planet-panel')) return;
        const point = this.canvasPoint(event);
        this.drag = {
            pointerId: event.pointerId,
            startX: point.x, startY: point.y,
            cameraX: this.camera.x, cameraY: this.camera.y,
            moved: false,
        };
        event.currentTarget.setPointerCapture?.(event.pointerId);
    }

    pointerMove(event) {
        const point = this.canvasPoint(event);
        if (this.drag && this.drag.pointerId === event.pointerId) {
            const dx = point.x - this.drag.startX, dy = point.y - this.drag.startY;
            if (Math.abs(dx) + Math.abs(dy) > 3) this.drag.moved = true;
            if (this.drag.moved && this.mode === 'galaxy') {
                this.camera.x = this.drag.cameraX - dx / this.zoom;
                this.camera.y = this.drag.cameraY - dy / this.zoom;
                this.hasUserCamera = true;
                this.startLoop();
            }
            return;
        }
        if (this.mode === 'galaxy') {
            this.updateHover(point);
            this.galaxyCanvasTarget.style.cursor = this.hoveredSystem ? 'pointer' : (this.drag?.moved ? 'grabbing' : 'grab');
        }
    }

    pointerUp(event) {
        if (!this.drag || this.drag.pointerId !== event.pointerId) return;
        const moved = this.drag.moved;
        const point = this.canvasPoint(event);
        this.drag = null;
        if (moved) return;
        if (this.mode === 'galaxy') {
            const system = this.nearestSystem(point);
            if (system) this.focusSystem(system);
        } else if (this.mode === 'system') {
            const planet = this.nearestPlanet(point);
            if (planet) this.selectPlanetById(planet.id);
        }
    }

    canvasPoint(event) {
        const rect = event.currentTarget.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    nearestSystem(point) {
        // Les emplacements sans système réel restent décoratifs et ne capturent pas la sélection.
        let nearest = null, distance = 36, placeholderDistance = Infinity;
        for (const system of this.systems) {
            const screen = this.galaxyPoint(system);
            const current = Math.hypot(screen.x - point.x, screen.y - point.y);
            if (current < distance) { nearest = system; distance = current; }
        }
        for (const placeholder of this.decorativeSystems || []) {
            const screen = this.galaxyPoint(placeholder);
            placeholderDistance = Math.min(placeholderDistance, Math.hypot(screen.x - point.x, screen.y - point.y));
        }
        return placeholderDistance < distance ? null : nearest;
    }

    nearestPlanet(point) {
        if (!this.selectedSystem) return null;
        let nearest = null, distance = Infinity;
        for (const planet of this.selectedSystem.planets) {
            if (!planet.sx || !planet.sy) continue;
            const current = Math.hypot(planet.sx - point.x, planet.sy - point.y);
            if (current < planet.visual.radius + 11 && current < distance) { nearest = planet; distance = current; }
        }
        return nearest;
    }

    updateHover(point) {
        const system = this.nearestSystem(point);
        if (this.hoveredSystem === system) return;
        this.hoveredSystem = system;
        if (!system) {
            this.tooltipTarget.hidden = true;
            this.coordinatesTarget.textContent = this.gameMap.galaxy || '—';
            return;
        }
        const count = system.known ? system.planets.length + ' planètes cartographiées' : 'Système non exploré';
        const origin = this.gameMap.origin;
        let distanceLabel = '';
        if (origin) {
            if (Number(origin.galaxyId) !== Number(system.galaxyId)) {
                distanceLabel = 'Trajet intergalactique indisponible';
            } else {
                const sameSystem = Number(origin.systemId) === Number(system.id);
                const localDistance = sameSystem
                    ? Math.abs(10 - Number(origin.position)) * 10
                    : 100 - Number(origin.position) * 10;
                const hyperDistance = sameSystem ? 0 : Math.hypot(Number(system.x) - Number(origin.x), Number(system.y) - Number(origin.y)) * 100;
                const distanceAu = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(localDistance + hyperDistance);
                distanceLabel = 'Depuis ' + origin.planetCoordinates + ' : ' + distanceAu + ' UA';
            }
        }
        this.tooltipTarget.textContent = [system.name + ' · ' + system.galaxy + ':' + system.number, count, distanceLabel].filter(Boolean).join(' · ');
        this.tooltipTarget.hidden = false;
        this.tooltipTarget.style.left = clamp(point.x + 18, 8, this.width - 240) + 'px';
        this.tooltipTarget.style.top = clamp(point.y + 18, 40, this.height - 70) + 'px';
        this.coordinatesTarget.textContent = system.galaxy + ':' + system.number;
    }

    wheel(event) {
        event.preventDefault();
        const rect = this.sceneTarget.getBoundingClientRect();
        const x = event.clientX - rect.left, y = event.clientY - rect.top;
        const previousZoom = Math.max(.001, this.zoom);
        const cursorWorldX = this.camera.x + (x - this.width / 2) / previousZoom;
        const cursorWorldY = this.camera.y + (y - this.height / 2) / previousZoom;
        const nextZoom = clamp(previousZoom * (event.deltaY < 0 ? 1.12 : .89), Math.max(.025, this.baseZoom * .2), Math.min(2.6, this.baseZoom * 8));
        this.camera.x = cursorWorldX - (x - this.width / 2) / nextZoom;
        this.camera.y = cursorWorldY - (y - this.height / 2) / nextZoom;
        this.zoom = this.targetZoom = nextZoom;
        this.hasUserCamera = true;
        this.drawFrame(0);
        this.startLoop();
    }

    focusSystem(system, planetId = null) {
        if (!system) return;
        this.selectedSystem = system;
        this.selectedPlanet = null;
        this.savedCamera = { camera: { ...this.camera }, zoom: this.targetZoom };
        this.systemContentTarget.replaceChildren();
        const template = this.element.querySelector('template[data-gv2-system="' + system.id + '"]');
        if (template) this.systemContentTarget.append(template.content.cloneNode(true));
        this.systemPanelTarget.hidden = false;
        this.planetPanelTarget.hidden = true;
        this.focusPanel(this.systemPanelTarget);
        this.systemBackTarget.hidden = false;
        this.planetBackTarget.hidden = true;
        this.minimapWrapTarget.hidden = true;
        this.fleetLogTarget.parentElement.hidden = true;
        this.locationTarget.textContent = system.name;
        this.coordinatesTarget.textContent = system.galaxy + ':' + system.number;
        this.mapHelpTarget.textContent = system.known ? 'Sélectionnez une planète dans le système ou ouvrez sa vue rapprochée.' : 'Ce signal n’est pas cartographié. Une sonde peut l’explorer.';
        const destinationZoom = clamp(this.baseZoom * 2.9, .25, 1.25);
        this.mode = this.motionReduced ? 'system' : 'transition-to-system';
        this.setCanvasMode('system');
        if (this.motionReduced) {
            this.camera = { x: system.wx, y: system.wy };
            this.zoom = this.targetZoom = destinationZoom;
            this.drawFrame(0);
        } else {
            this.animateCamera(system.wx, system.wy, destinationZoom, 520, () => {
                this.mode = 'system';
                this.drawFrame(0);
            });
        }
        if (planetId) window.setTimeout(() => this.selectPlanetById(planetId), this.motionReduced ? 0 : 560);
    }

    animateCamera(x, y, zoom, duration, done) {
        this.cameraAnimation = {
            startX: this.camera.x, startY: this.camera.y, startZoom: this.targetZoom,
            endX: x, endY: y, endZoom: zoom, started: performance.now(), duration, done,
        };
        this.startLoop();
    }

    stepCameraAnimation(time) {
        const animation = this.cameraAnimation;
        if (!animation) return;
        const progress = clamp((time - animation.started) / animation.duration, 0, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        this.camera.x = animation.startX + (animation.endX - animation.startX) * eased;
        this.camera.y = animation.startY + (animation.endY - animation.startY) * eased;
        this.targetZoom = animation.startZoom + (animation.endZoom - animation.startZoom) * eased;
        if (progress === 1) {
            this.camera.x = animation.endX;
            this.camera.y = animation.endY;
            this.targetZoom = animation.endZoom;
            this.cameraAnimation = null;
            animation.done?.();
        }
    }

    toGalaxy() {
        if (this.mode === 'planet') {
            this.toSystem();
            return;
        }
        this.mode = this.motionReduced ? 'galaxy' : 'transition-to-galaxy';
        this.setCanvasMode('galaxy');
        this.systemPanelTarget.hidden = true;
        this.planetPanelTarget.hidden = true;
        this.systemBackTarget.hidden = true;
        this.planetBackTarget.hidden = true;
        this.minimapWrapTarget.hidden = false;
        this.fleetLogTarget.parentElement.hidden = false;
        this.locationTarget.textContent = this.gameMap.galaxy || this.gameMap.universe;
        this.coordinatesTarget.textContent = this.gameMap.galaxy || '—';
        this.mapHelpTarget.textContent = 'Glisser pour naviguer · Molette pour zoomer · Cliquer sur un système pour l’examiner';
        this.searchInputTarget.focus({ preventScroll: true });
        const camera = this.savedCamera?.camera || this.baseCamera;
        const zoom = this.savedCamera?.zoom || this.baseZoom;
        const done = () => {
            this.mode = 'galaxy';
            this.selectedSystem = null;
            this.selectedPlanet = null;
            this.hasUserCamera = false;
            this.drawFrame(0);
        };
        if (this.motionReduced) {
            this.camera = { ...camera };
            this.zoom = this.targetZoom = zoom;
            done();
        } else this.animateCamera(camera.x, camera.y, zoom, 420, done);
    }

    setCanvasMode(mode) {
        this.galaxyCanvasTarget.classList.toggle('is-active', mode === 'galaxy');
        this.systemCanvasTarget.classList.toggle('is-active', mode === 'system');
        this.planetCanvasTarget.classList.toggle('is-active', mode === 'planet');
        if (mode !== 'galaxy') {
            this.hoveredSystem = null;
            this.tooltipTarget.hidden = true;
        }
    }

    selectPlanet(event) {
        const id = Number(event.currentTarget.dataset.planetId);
        this.selectPlanetById(id);
    }

    selectPlanetById(id) {
        if (!this.selectedSystem) return;
        const planet = this.selectedSystem.planets.find((candidate) => Number(candidate.id) === Number(id));
        if (!planet) return;
        this.selectedPlanet = planet;
        const template = this.element.querySelector('template[data-gv2-planet="' + planet.id + '"]');
        if (template) {
            this.planetContentTarget.replaceChildren(template.content.cloneNode(true));
            this.planetPanelTarget.hidden = false;
            this.systemPanelTarget.hidden = true;
            this.focusPanel(this.planetPanelTarget);
        }
        this.systemContentTarget.querySelectorAll('.gv2-planet-choice').forEach((button) => {
            button.classList.toggle('is-selected', Number(button.dataset.planetId) === Number(planet.id));
        });
        this.preparePlanetTexture(planet);
        this.drawFrame(0);
    }

    closePlanet() {
        const id = this.selectedPlanet?.id;
        this.selectedPlanet = null;
        this.planetPanelTarget.hidden = true;
        if (!this.planetBackTarget.hidden) this.planetBackTarget.focus({ preventScroll: true });
        else {
            this.systemPanelTarget.hidden = false;
            this.systemContentTarget.querySelector('[data-planet-id="' + id + '"]')?.focus({ preventScroll: true });
        }
        this.drawFrame(0);
    }

    focusPanel(panel) {
        const heading = panel.querySelector('h2');
        if (!heading) return;
        panel.scrollTop = 0;
        heading.tabIndex = -1;
        heading.focus({ preventScroll: true });
    }

    navigateBack(event) {
        if (event.key !== 'Escape' || event.defaultPrevented || event.target.closest('input, select, textarea')) return;
        if (!this.planetBackTarget.hidden) this.toSystem();
        else if (!this.planetPanelTarget.hidden) this.closePlanet();
        else if (!this.systemPanelTarget.hidden) this.toGalaxy();
        else return;
        event.preventDefault();
    }

    enterPlanetView() {
        if (!this.selectedPlanet) return;
        this.mode = this.motionReduced ? 'planet' : 'transition-to-planet';
        this.setCanvasMode('planet');
        this.systemPanelTarget.hidden = true;
        this.planetPanelTarget.hidden = false;
        this.systemBackTarget.hidden = true;
        this.planetBackTarget.hidden = false;
        this.minimapWrapTarget.hidden = true;
        this.fleetLogTarget.parentElement.hidden = true;
        this.locationTarget.textContent = this.selectedPlanet.name;
        this.coordinatesTarget.textContent = this.selectedPlanet.coordinates;
        this.mapHelpTarget.textContent = 'Représentation visuelle procédurale · utilisez ‹ Système pour revenir';
        if (this.motionReduced) {
            this.mode = 'planet';
            this.drawFrame(0);
        } else {
            window.setTimeout(() => { this.mode = 'planet'; this.drawFrame(0); }, 420);
        }
    }

    toSystem() {
        if (!this.selectedSystem) return;
        this.mode = this.motionReduced ? 'system' : 'transition-to-system-from-planet';
        this.setCanvasMode('system');
        this.planetPanelTarget.hidden = true;
        this.systemPanelTarget.hidden = false;
        this.systemBackTarget.hidden = false;
        this.planetBackTarget.hidden = true;
        const choice = this.systemContentTarget.querySelector('[data-planet-id="' + this.selectedPlanet?.id + '"]');
        if (choice) choice.focus({ preventScroll: true });
        else this.focusPanel(this.systemPanelTarget);
        this.minimapWrapTarget.hidden = true;
        this.fleetLogTarget.parentElement.hidden = true;
        this.locationTarget.textContent = this.selectedSystem.name;
        this.coordinatesTarget.textContent = this.selectedSystem.galaxy + ':' + this.selectedSystem.number;
        this.mapHelpTarget.textContent = 'Sélectionnez une planète dans le système ou ouvrez sa vue rapprochée.';
        if (this.motionReduced) {
            this.mode = 'system';
            this.drawFrame(0);
        } else {
            window.setTimeout(() => { this.mode = 'system'; this.drawFrame(0); }, 420);
        }
    }

    systemCanvasClick(event) {
        if (this.mode !== 'system') return;
        const planet = this.nearestPlanet(this.canvasPoint(event));
        if (!planet) return;
        const now = performance.now();
        if (this.lastPlanetClick && this.lastPlanetClick.id === planet.id && now - this.lastPlanetClick.time < 360) {
            this.enterPlanetView();
        } else {
            this.selectPlanetById(planet.id);
        }
        this.lastPlanetClick = { id: planet.id, time: now };
    }

    systemCanvasDoubleClick(event) {
        if (this.mode !== 'system') return;
        const planet = this.nearestPlanet(this.canvasPoint(event));
        if (!planet) return;
        this.selectPlanetById(planet.id);
        this.enterPlanetView();
    }

    drawSystem(dt) {
        const ctx = this.systemCanvasTarget.getContext('2d');
        ctx.clearRect(0, 0, this.width, this.height);
        const system = this.selectedSystem;
        this.drawSystemBackdrop(ctx, system);
        if (!system) return;
        const scale = clamp(Math.min(this.width / 1160, this.height / 740), .48, 1.05);
        const centerX = Math.min(this.width * .43, this.width - 210);
        const centerY = this.height * .53;
        const starColor = system.starColor || '#ffe28c';
        const orbitScale = scale * Math.min(1, (this.width * .38) / 510);
        this.drawStar(ctx, centerX, centerY, starColor, this.elapsed);
        const planets = [...system.planets].sort((a, b) => Math.sin(a.visual.angle) - Math.sin(b.visual.angle));
        for (const planet of planets) {
            const visual = planet.visual;
            const radius = (66 + Number(planet.position) * 38) * orbitScale;
            visual.angle += (dt || 0) * visual.speed;
            const x = centerX + Math.cos(visual.angle) * radius;
            const y = centerY + Math.sin(visual.angle) * radius * .38;
            visual.sx = x;
            visual.sy = y;
            visual.screenRadius = visual.radius * scale;
            ctx.beginPath();
            ctx.ellipse(centerX, centerY, radius, radius * .38, 0, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(209,225,246,.095)';
            ctx.lineWidth = .7;
            ctx.stroke();
            const depth = .72 + .28 * (Math.sin(visual.angle) + 1) / 2;
            const planetRadius = visual.radius * scale * depth;
            if (visual.ring) this.drawRing(ctx, x, y, planetRadius, visual.palette.glow, true);
            const halo = ctx.createRadialGradient(x, y, 0, x, y, planetRadius * 3.4);
            halo.addColorStop(0, visual.palette.glow + '38');
            halo.addColorStop(1, visual.palette.glow + '00');
            ctx.fillStyle = halo;
            ctx.fillRect(x - planetRadius * 3.4, y - planetRadius * 3.4, planetRadius * 6.8, planetRadius * 6.8);
            const globe = ctx.createRadialGradient(x - planetRadius * .38, y - planetRadius * .35, 0, x, y, planetRadius);
            globe.addColorStop(0, this.rgb(visual.palette.cloud, .95));
            globe.addColorStop(.4, this.rgb(visual.palette.land, 1));
            globe.addColorStop(.72, this.rgb(visual.palette.sea, 1));
            globe.addColorStop(1, 'rgba(3,8,17,.96)');
            ctx.beginPath();
            ctx.arc(x, y, planetRadius, 0, Math.PI * 2);
            ctx.fillStyle = globe;
            ctx.fill();
            ctx.save();
            ctx.beginPath();
            ctx.arc(x, y, planetRadius, 0, Math.PI * 2);
            ctx.clip();
            ctx.fillStyle = 'rgba(0,0,0,.26)';
            ctx.fillRect(x, y - planetRadius, planetRadius, planetRadius * 2);
            ctx.restore();
            if (visual.ring) this.drawRing(ctx, x, y, planetRadius, visual.palette.glow, false);
            if (this.selectedPlanet?.id === planet.id) {
                ctx.beginPath();
                ctx.arc(x, y, planetRadius + 7, 0, Math.PI * 2);
                ctx.strokeStyle = '#00d4ff';
                ctx.setLineDash([3, 4]);
                ctx.stroke();
                ctx.setLineDash([]);
            }
            ctx.font = '11px "Chakra Petch", sans-serif';
            ctx.textAlign = 'center';
            ctx.fillStyle = 'rgba(221,232,248,.82)';
            ctx.fillText(planet.name, x, y + planetRadius + 14);
        }
        this.drawLocalFlights(ctx, system, centerX, centerY, orbitScale);
    }

    drawStar(ctx, x, y, color, time) {
        const pulse = 1 + .022 * Math.sin(time * 1.2) + .012 * Math.sin(time * 2.3);
        for (let i = 3; i >= 0; i--) {
            const radius = (105 + i * 42) * pulse;
            const glow = ctx.createRadialGradient(x, y, radius * .12, x, y, radius);
            glow.addColorStop(0, color + '22');
            glow.addColorStop(.42, color + '0b');
            glow.addColorStop(1, color + '00');
            ctx.fillStyle = glow;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.save();
        ctx.translate(x, y);
        for (let i = 0; i < 5; i++) {
            const angle = i * 1.257 + time * .018;
            const length = 7 + 4 * Math.sin(time * .5 + i * 2);
            ctx.beginPath();
            ctx.moveTo(Math.cos(angle) * 26, Math.sin(angle) * 26);
            ctx.quadraticCurveTo(Math.cos(angle + .12) * (30 + length), Math.sin(angle + .12) * (30 + length), Math.cos(angle + .25) * 26, Math.sin(angle + .25) * 26);
            ctx.strokeStyle = color + '55';
            ctx.lineWidth = 1.4;
            ctx.stroke();
        }
        ctx.restore();
        const core = ctx.createRadialGradient(x - 4, y - 4, 0, x, y, 36 * pulse);
        core.addColorStop(0, '#ffffff');
        core.addColorStop(.14, color);
        core.addColorStop(.65, color + 'a0');
        core.addColorStop(1, color + '00');
        ctx.fillStyle = core;
        ctx.beginPath();
        ctx.arc(x, y, 36 * pulse, 0, Math.PI * 2);
        ctx.fill();
    }

    drawRing(ctx, x, y, radius, color, back) {
        ctx.save();
        ctx.beginPath();
        ctx.ellipse(x, y, radius * 2.15, radius * .52, -.18, back ? Math.PI : 0, back ? Math.PI * 2 : Math.PI);
        ctx.strokeStyle = color + (back ? '45' : '75');
        ctx.lineWidth = Math.max(1.2, radius * .28);
        ctx.stroke();
        ctx.restore();
    }

    drawLocalStars(ctx) {
        for (const star of this.backgroundStars) {
            const alpha = star.alpha * (.6 + .4 * Math.sin(this.elapsed + star.phase));
            ctx.fillStyle = 'rgba(201,216,239,' + alpha + ')';
            ctx.fillRect(star.x * this.width, star.y * this.height, star.size, star.size);
        }
    }

    drawLocalFlights(ctx, system, centerX, centerY, scale) {
        const now = Date.now() + this.serverClockOffset;
        for (const flight of this.flights) {
            if (!flight.originSystem || !flight.destinationSystem) continue;
            if (flight.originSystem.id !== system.id && flight.destinationSystem.id !== system.id) continue;
            const originPlanet = this.findPlanetByName(flight.originName, flight.originSystem);
            const targetPlanet = this.findPlanetByName(flight.destinationName, flight.destinationSystem);
            const startPlanet = flight.originSystem.id === system.id ? originPlanet : null;
            const endPlanet = flight.destinationSystem.id === system.id ? targetPlanet : null;
            const start = startPlanet?.visual ? { x: centerX + Math.cos(startPlanet.visual.angle) * startPlanet.visual.orbitRadius * scale, y: centerY + Math.sin(startPlanet.visual.angle) * startPlanet.visual.orbitRadius * scale * .38 } : { x: this.width * .04, y: centerY };
            const end = endPlanet?.visual ? { x: centerX + Math.cos(endPlanet.visual.angle) * endPlanet.visual.orbitRadius * scale, y: centerY + Math.sin(endPlanet.visual.angle) * endPlanet.visual.orbitRadius * scale * .38 } : { x: this.width * .9, y: centerY };
            const departure = Date.parse(flight.departureAt || ''), arrival = Date.parse(flight.arrivalAt || '');
            if (flight.status === 'attacking' || flight.status === 'defending' || flight.status === 'orbiting' || flight.status === 'orbit_pending') {
                const point = endPlanet?.visual ? end : { x: this.width * .9, y: centerY };
                const color = missionColors[flight.mission] || '#00d4ff';
                ctx.strokeStyle = color;
                ctx.globalAlpha = .55 + .25 * Math.sin(this.elapsed * 2 + Number(flight.id));
                ctx.beginPath();
                ctx.arc(point.x, point.y, 8 + 3 * Math.sin(this.elapsed * 2), 0, Math.PI * 2);
                ctx.stroke();
                ctx.globalAlpha = 1;
                continue;
            }
            if (!Number.isFinite(departure) || !Number.isFinite(arrival) || arrival <= departure) continue;
            let progress = clamp((now - departure) / (arrival - departure), 0, 1);
            if (flight.status === 'returning') progress = 1 - progress;
            const x = start.x + (end.x - start.x) * progress;
            const y = start.y + (end.y - start.y) * progress - Math.sin(progress * Math.PI) * 36;
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fillStyle = missionColors[flight.mission] || '#00d4ff';
            ctx.shadowColor = ctx.fillStyle;
            ctx.shadowBlur = 12;
            ctx.fill();
            ctx.shadowBlur = 0;
        }
    }

    findPlanetByName(name, system) {
        return system.planets.find((planet) => planet.name === name) || null;
    }

    drawPlanet(dt) {
        const ctx = this.planetCanvasTarget.getContext('2d');
        ctx.clearRect(0, 0, this.width, this.height);
        const planet = this.selectedPlanet;
        this.drawPlanetBackdrop(ctx, planet);
        if (!planet) return;
        const visual = planet.visual;
        if (!visual.sphereCanvas) this.preparePlanetTexture(planet);
        const radius = Math.min(this.width, this.height) * .27;
        const x = this.width * .59, y = this.height * .53;
        const color = visual.palette.glow;
        for (let i = 5; i >= 0; i--) {
            const glowRadius = radius + 7 + i * 9;
            const atmosphere = ctx.createRadialGradient(x, y, radius - 6, x, y, glowRadius);
            atmosphere.addColorStop(0, color + '00');
            atmosphere.addColorStop(.58, color + '0' + Math.min(9, i + 2));
            atmosphere.addColorStop(1, color + '00');
            ctx.fillStyle = atmosphere;
            ctx.beginPath();
            ctx.arc(x, y, glowRadius, 0, Math.PI * 2);
            ctx.fill();
        }
        if (visual.ring) this.drawPlanetRings(ctx, x, y, radius, color, true);
        this.drawDecorativeMoon(ctx, visual, x, y, radius, true);
        if (!this.motionReduced) visual.rotation += dt * .14;
        this.renderSphere(visual, radius, visual.rotation);
        ctx.save();
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.clip();
        ctx.drawImage(visual.sphereCanvas, x - radius, y - radius, radius * 2, radius * 2);
        ctx.restore();
        ctx.save();
        ctx.beginPath();
        ctx.arc(x, y, radius * 1.006, 0, Math.PI * 2);
        ctx.strokeStyle = color + '99';
        ctx.lineWidth = Math.max(1, radius * .014);
        ctx.shadowColor = color;
        ctx.shadowBlur = Math.max(5, radius * .055);
        ctx.stroke();
        ctx.restore();
        if (visual.ring) this.drawPlanetRings(ctx, x, y, radius, color, false);
        this.drawDecorativeMoon(ctx, visual, x, y, radius, false);
        const light = ctx.createRadialGradient(x - radius * .48, y - radius * .42, 0, x - radius * .35, y - radius * .3, radius * 1.7);
        light.addColorStop(0, 'rgba(255,255,255,.12)');
        light.addColorStop(.35, 'rgba(100,180,255,.035)');
        light.addColorStop(1, 'rgba(0,0,0,.42)');
        ctx.fillStyle = light;
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fill();
    }

    sampleTextureNoise(grid, columns, rows, u, v) {
        const gx = ((u % 1) + 1) % 1 * columns;
        const x0 = Math.floor(gx) % columns;
        const x1 = (x0 + 1) % columns;
        const txRaw = gx - Math.floor(gx);
        const tx = txRaw * txRaw * (3 - 2 * txRaw);
        const gy = clamp(v, 0, 1) * (rows - 1);
        const y0 = Math.floor(gy);
        const y1 = Math.min(rows - 1, y0 + 1);
        const tyRaw = gy - y0;
        const ty = tyRaw * tyRaw * (3 - 2 * tyRaw);
        const top = grid[y0 * columns + x0] * (1 - tx) + grid[y0 * columns + x1] * tx;
        const bottom = grid[y1 * columns + x0] * (1 - tx) + grid[y1 * columns + x1] * tx;
        return top * (1 - ty) + bottom * ty;
    }

    preparePlanetTexture(planet) {
        const visual = planet.visual;
        const width = 384, height = 192;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        const image = context.createImageData(width, height);
        const pixels = image.data;
        const random = this.seededRandom(visual.seed);
        const palette = visual.palette;
        const octaves = [
            { columns: 4, rows: 3, weight: .48 },
            { columns: 8, rows: 5, weight: .25 },
            { columns: 16, rows: 9, weight: .14 },
            { columns: 32, rows: 17, weight: .08 },
            { columns: 64, rows: 33, weight: .05 },
        ].map((octave) => ({
            ...octave,
            values: Array.from({ length: octave.columns * octave.rows }, () => random()),
        }));
        const smooth = (value, low, high) => {
            const t = clamp((value - low) / (high - low), 0, 1);
            return t * t * (3 - 2 * t);
        };

        for (let y = 0; y < height; y += 1) {
            const v = y / (height - 1);
            for (let x = 0; x < width; x += 1) {
                const u = x / width;
                let elevation = 0, cloudNoise = 0;
                for (let octave = 0; octave < octaves.length; octave += 1) {
                    const layer = octaves[octave];
                    const noise = this.sampleTextureNoise(layer.values, layer.columns, layer.rows, u, v);
                    elevation += noise * layer.weight;
                    if (octave < 4) cloudNoise += noise * (octave === 0 ? .38 : octave === 1 ? .32 : .2);
                }

                const latitude = Math.abs(v * 2 - 1);
                let landMix;
                if (visual.kind === 'gas') {
                    const bands = Math.sin(v * Math.PI * 28 + elevation * 8) * .18;
                    const turbulence = Math.sin(u * 18 + elevation * 7 + Math.cos(v * 21) * 2) * .07;
                    landMix = smooth(.5 + bands + turbulence + elevation * .2, .42, .62);
                } else {
                    landMix = smooth(elevation + (visual.kind === 'ice' ? .04 : 0), .48, .56);
                    if (visual.kind === 'ice') landMix = Math.max(landMix, smooth(latitude, .72, .91) * .82);
                }

                const pixel = (y * width + x) * 4;
                const coast = 1 - Math.min(1, Math.abs(elevation - .52) * 24);
                let red = palette.sea[0] * (1 - landMix) + palette.land[0] * landMix;
                let green = palette.sea[1] * (1 - landMix) + palette.land[1] * landMix;
                let blue = palette.sea[2] * (1 - landMix) + palette.land[2] * landMix;
                const surfaceShade = (elevation - .5) * 20 + coast * 8;
                red += surfaceShade;
                green += surfaceShade;
                blue += surfaceShade;

                const cloudMask = smooth(cloudNoise + Math.sin(v * Math.PI * 4 + elevation * 5) * .06, .66, .79);
                const cloudAmount = visual.kind === 'gas' ? cloudMask * .72 : cloudMask * .46;
                red = red * (1 - cloudAmount) + palette.cloud[0] * cloudAmount;
                green = green * (1 - cloudAmount) + palette.cloud[1] * cloudAmount;
                blue = blue * (1 - cloudAmount) + palette.cloud[2] * cloudAmount;
                const variation = (random() - .5) * 5;
                pixels[pixel] = clamp(red + variation, 0, 255);
                pixels[pixel + 1] = clamp(green + variation, 0, 255);
                pixels[pixel + 2] = clamp(blue + variation, 0, 255);
                pixels[pixel + 3] = 255;
            }
        }
        context.putImageData(image, 0, 0);
        visual.texture = canvas;
        visual.textureWidth = width;
        visual.textureHeight = height;
        visual.texturePixels = pixels;
        visual.sphereCanvas = document.createElement('canvas');
        visual.sphereCanvas.width = visual.sphereCanvas.height = visual.sphereSize;
        visual.sphereContext = visual.sphereCanvas.getContext('2d');
    }

    renderSphere(visual, radius, rotation) {
        const size = visual.sphereSize, pixels = new Uint8ClampedArray(size * size * 4);
        const texture = visual.texturePixels;
        const textureWidth = visual.textureWidth, textureHeight = visual.textureHeight;
        const lightX = -.46, lightY = -.28, lightZ = .84;
        const atmosphereColor = visual.palette.glow.match(/[a-f0-9]{2}/gi)?.map((part) => parseInt(part, 16)) || [126, 185, 231];
        for (let y = 0; y < size; y++) {
            for (let x = 0; x < size; x++) {
                const nx = (x - size / 2 + .5) / (size / 2);
                const ny = (y - size / 2 + .5) / (size / 2);
                const distance = nx * nx + ny * ny;
                if (distance >= 1) continue;
                const nz = Math.sqrt(1 - distance);
                const longitude = Math.atan2(nx, nz) / (Math.PI * 2) + rotation / (Math.PI * 2);
                const latitude = Math.acos(clamp(ny, -1, 1)) / Math.PI;
                const tx = Math.floor(((longitude % 1 + 1) % 1) * (textureWidth - 1));
                const ty = clamp(Math.floor(latitude * (textureHeight - 1)), 0, textureHeight - 1);
                const index = (ty * textureWidth + tx) * 4;
                const diffuse = Math.max(0, nx * lightX + ny * lightY + nz * lightZ);
                const illumination = .075 + diffuse * .925;
                const specular = Math.pow(diffuse, visual.kind === 'gas' ? 28 : 42) * (visual.kind === 'gas' ? 18 : 30);
                const edge = Math.pow(1 - nz, 2.15) * .24;
                const pixelIndex = (y * size + x) * 4;
                pixels[pixelIndex] = clamp(texture[index] * illumination + specular + atmosphereColor[0] * edge, 0, 255);
                pixels[pixelIndex + 1] = clamp(texture[index + 1] * illumination + specular + atmosphereColor[1] * edge, 0, 255);
                pixels[pixelIndex + 2] = clamp(texture[index + 2] * illumination + specular + atmosphereColor[2] * edge, 0, 255);
                pixels[pixelIndex + 3] = 255;
            }
        }
        const image = new ImageData(pixels, size, size);
        visual.sphereContext.putImageData(image, 0, 0);
    }

    drawPlanetRings(ctx, x, y, radius, color, behind) {
        ctx.save();
        ctx.translate(x, y);
        ctx.scale(1, .32);
        const inner = radius * 1.35, outer = radius * 1.95;
        const gradient = ctx.createRadialGradient(0, 0, inner, 0, 0, outer);
        gradient.addColorStop(0, color + '05');
        gradient.addColorStop(.35, color + (behind ? '55' : '88'));
        gradient.addColorStop(1, color + '05');
        ctx.beginPath();
        ctx.arc(0, 0, outer, behind ? Math.PI : 0, behind ? Math.PI * 2 : Math.PI);
        ctx.lineTo(Math.cos(Math.PI) * inner, Math.sin(Math.PI) * inner);
        ctx.arc(0, 0, inner, Math.PI, 0, true);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();
        ctx.restore();
    }

    drawDecorativeMoon(ctx, visual, x, y, radius, behind) {
        const angle = this.elapsed * .28 + visual.moonAngle;
        if ((Math.sin(angle) < 0) !== behind) return;
        const moonX = x + Math.cos(angle) * (radius + visual.moonDistance);
        const moonY = y + Math.sin(angle) * (radius + visual.moonDistance) * .35;
        ctx.beginPath();
        ctx.arc(moonX, moonY, visual.moonSize, 0, Math.PI * 2);
        ctx.fillStyle = '#bac9d9';
        ctx.shadowColor = '#9fd9ff';
        ctx.shadowBlur = 8;
        ctx.fill();
        ctx.shadowBlur = 0;
    }

    rgb(color, alpha) {
        return 'rgba(' + color.join(',') + ',' + alpha + ')';
    }

    updateFlightTimers() {
        const now = Date.now() + this.serverClockOffset;
        this.element.querySelectorAll('[data-galaxy-v2-countdown]').forEach((element) => {
            const arrival = Date.parse(element.dataset.galaxyV2Countdown || '');
            if (!Number.isFinite(arrival)) return;
            const remaining = Math.max(0, Math.floor((arrival - now) / 1000));
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            element.textContent = remaining > 0 ? 'Arrivée dans ' + minutes + ' min ' + seconds.toString().padStart(2, '0') + ' s' : 'Arrivée imminente';
        });
    }

    indexSearch() {
        this.searchIndex = [];
        for (const system of this.systems) {
            this.searchIndex.push({
                type: 'system', id: system.id, system,
                name: system.name,
                detail: system.galaxy + ':' + system.number + (system.known ? ' · ' + system.planets.length + ' planètes cartographiées' : ' · signal inconnu'),
            });
            if (system.known) {
                for (const planet of system.planets) {
                    this.searchIndex.push({
                        type: 'planet', id: planet.id, system, planet, name: planet.name,
                        detail: planet.coordinates + ' · ' + (planet.ownedByCurrentPlayer ? 'Votre colonie' : (planet.ownerName || (planet.npc ? 'Monde PNJ' : 'Inhabitée'))),
                    });
                }
            }
        }
    }

    search() {
        const query = this.searchInputTarget.value.trim().toLocaleLowerCase('fr');
        this.searchClearTarget.classList.toggle('is-visible', query.length > 0);
        this.searchClearTarget.hidden = query.length === 0;
        this.searchResultsTarget.replaceChildren();
        if (query.length < 2) {
            this.hideSearchResults();
            return;
        }
        const matches = this.searchIndex
            .map((item) => {
                const name = item.name.toLocaleLowerCase('fr');
                const coords = item.detail.toLocaleLowerCase('fr');
                const score = name === query ? 100 : name.startsWith(query) ? 80 : name.includes(query) ? 50 : coords.includes(query) ? 25 : 0;
                return score ? { ...item, score } : null;
            })
            .filter(Boolean)
            .sort((a, b) => b.score - a.score)
            .slice(0, 12);
        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'gv2-search-empty';
            empty.textContent = 'Aucun résultat connu pour « ' + this.searchInputTarget.value.trim() + ' ».';
            this.searchResultsTarget.append(empty);
        } else {
            matches.forEach((item, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'gv2-search-result';
                button.dataset.index = String(index);
                const icon = document.createElement('span');
                icon.className = 'gv2-search-result-icon';
                icon.textContent = item.type === 'system' ? '✦' : '◉';
                const main = document.createElement('span');
                main.className = 'gv2-search-result-main';
                const name = document.createElement('strong');
                name.textContent = item.name;
                const detail = document.createElement('small');
                detail.textContent = item.detail;
                main.append(name, detail);
                button.append(icon, main);
                button.addEventListener('click', () => this.selectSearchResult(item));
                this.searchResultsTarget.append(button);
            });
        }
        this.searchResultsTarget.hidden = false;
        this.searchStatusTarget.textContent = matches.length + ' résultat' + (matches.length === 1 ? '' : 's') + ' affiché' + (matches.length === 1 ? '' : 's');
    }

    searchKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            this.hideSearchResults();
            this.searchInputTarget.focus({ preventScroll: true });
            return;
        }
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && this.searchResultsTarget.hidden) this.search();
        const items = Array.from(this.searchResultsTarget.querySelectorAll('.gv2-search-result'));
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && items.length) {
            event.preventDefault();
            const current = items.indexOf(document.activeElement);
            const next = current < 0 ? (event.key === 'ArrowDown' ? 0 : items.length - 1) : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next].focus();
        }
        if (event.key === 'Enter' && event.currentTarget === this.searchInputTarget) {
            event.preventDefault();
            if (items.length) items[0].click();
        }
    }

    selectSearchResult(item) {
        this.hideSearchResults();
        if (item.type === 'system') this.focusSystem(item.system);
        else this.focusSystem(item.system, item.planet.id);
    }

    hideSearchResults() {
        this.searchResultsTarget.hidden = true;
        this.searchStatusTarget.textContent = '';
    }

    clearSearch() {
        this.searchInputTarget.value = '';
        this.hideSearchResults();
        this.searchClearTarget.hidden = true;
        this.searchClearTarget.classList.remove('is-visible');
        this.searchInputTarget.focus();
    }

    changeGalaxy() {
        this.galaxySelectTarget.form.requestSubmit();
    }

    zoomIn() {
        this.targetZoom = clamp(this.targetZoom * 1.2, Math.max(.025, this.baseZoom * .2), Math.min(2.6, this.baseZoom * 8));
        if (this.motionReduced) this.zoom = this.targetZoom;
        this.startLoop();
    }

    zoomOut() {
        this.targetZoom = clamp(this.targetZoom * .83, Math.max(.025, this.baseZoom * .2), Math.min(2.6, this.baseZoom * 8));
        if (this.motionReduced) this.zoom = this.targetZoom;
        this.startLoop();
    }

    toggleMotion() {
        this.motionReduced = !this.motionReduced;
        this.updateMotionButton();
        if (this.motionReduced) this.stopLoop();
        else this.startLoop();
        this.drawFrame(0);
    }

    updateMotionButton() {
        if (!this.hasMotionToggleTarget) return;
        this.motionToggleTarget.setAttribute('aria-pressed', String(this.motionReduced));
        this.motionToggleTarget.setAttribute('aria-label', this.motionReduced ? 'Réactiver les animations' : 'Réduire les animations');
        this.motionToggleTarget.title = this.motionReduced ? 'Animations réduites' : 'Réduire les animations';
        this.motionToggleTarget.classList.toggle('is-reduced', this.motionReduced);
    }

    minimapPointer(event) {
        event.preventDefault();
        const rect = this.minimapCanvasTarget.getBoundingClientRect();
        const x = event.clientX - rect.left, y = event.clientY - rect.top;
        const bounds = this.systemBounds;
        const spanX = Math.max(1, bounds.maxX - bounds.minX), spanY = Math.max(1, bounds.maxY - bounds.minY);
        const scale = Math.min((rect.width - 16) / spanX, (rect.height - 16) / spanY);
        const worldX = (bounds.minX + bounds.maxX) / 2 + (x - rect.width / 2) / scale;
        const worldY = (bounds.minY + bounds.maxY) / 2 + (y - rect.height / 2) / scale;
        let nearest = null, nearestDistance = 12 / scale, placeholderDistance = Infinity;
        for (const system of this.systems) {
            const distance = Math.hypot(system.wx - worldX, system.wy - worldY);
            if (distance < nearestDistance) { nearest = system; nearestDistance = distance; }
        }
        for (const placeholder of this.decorativeSystems || []) {
            placeholderDistance = Math.min(placeholderDistance, Math.hypot(placeholder.wx - worldX, placeholder.wy - worldY));
        }
        if (nearest && nearestDistance <= placeholderDistance) {
            this.focusSystem(nearest);
            return;
        }
        this.camera.x = worldX;
        this.camera.y = worldY;
        this.hasUserCamera = true;
        this.startLoop();
    }
}

function lerpColor(from, to, amount) {
    return Math.round(from + (to - from) * amount);
}
