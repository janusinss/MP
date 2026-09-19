/**
 * ⚡ Overdrive Creative Engineering Pipeline
 * - ThreeUI Ambient WebGL Particle Canvas (Clamped DPR, Lerped Pointer, IO Lifecycle)
 * - 21st.dev Spotlight Physics & Bento Matrix Cursor Illumination
 * - Lenis Smooth Scroll Engine + GSAP ScrollTrigger Synchronization
 */

(function () {
    'use strict';

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // -------------------------------------------------------------
    // 1. THREE.JS AMBIENT WEBGL BACKGROUND SHADER SUITE
    // -------------------------------------------------------------
    function initAmbientCanvas() {
        const canvas = document.getElementById('ambient-canvas');
        const container = document.getElementById('hero-overdrive');
        if (!canvas || !container || typeof THREE === 'undefined') return;

        let width = container.clientWidth;
        let height = container.clientHeight;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(55, width / height, 0.1, 1000);
        camera.position.z = 45;

        let renderer;
        try {
            renderer = new THREE.WebGLRenderer({
                canvas: canvas,
                alpha: true,
                antialias: true,
                powerPreference: 'high-performance'
            });
        } catch (e) {
            console.warn('WebGL initialization failed, falling back to CSS background.', e);
            return;
        }

        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

        // Create Soft Particle Sprite
        function createParticleTexture() {
            const pCanvas = document.createElement('canvas');
            pCanvas.width = 64;
            pCanvas.height = 64;
            const ctx = pCanvas.getContext('2d');
            const gradient = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
            gradient.addColorStop(0, 'rgba(255, 255, 255, 1)');
            gradient.addColorStop(0.35, 'rgba(255, 255, 255, 0.6)');
            gradient.addColorStop(0.8, 'rgba(255, 255, 255, 0.1)');
            gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, 64, 64);
            const texture = new THREE.CanvasTexture(pCanvas);
            return texture;
        }

        // Particle System Geometry & Colors
        const particleCount = 450;
        const positions = new Float32Array(particleCount * 3);
        const originalPositions = new Float32Array(particleCount * 3);
        const colors = new Float32Array(particleCount * 3);
        const speeds = new Float32Array(particleCount);

        const brandColors = [
            new THREE.Color(0x4A, 0x74, 0x5B), // Sage
            new THREE.Color(0x5E, 0x91, 0x73), // Forest Light
            new THREE.Color(0xC9, 0x8B, 0x32), // Golden Amber
            new THREE.Color(0x8C, 0xA8, 0x96)  // Muted Leaf
        ];

        for (let i = 0; i < particleCount; i++) {
            const i3 = i * 3;
            // Spread across 3D space
            const x = (Math.random() - 0.5) * 80;
            const y = (Math.random() - 0.5) * 50;
            const z = (Math.random() - 0.5) * 40;

            positions[i3] = x;
            positions[i3 + 1] = y;
            positions[i3 + 2] = z;

            originalPositions[i3] = x;
            originalPositions[i3 + 1] = y;
            originalPositions[i3 + 2] = z;

            speeds[i] = 0.4 + Math.random() * 0.8;

            const color = brandColors[Math.floor(Math.random() * brandColors.length)];
            colors[i3] = color.r;
            colors[i3 + 1] = color.g;
            colors[i3 + 2] = color.b;
        }

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

        const material = new THREE.PointsMaterial({
            size: 2.2,
            map: createParticleTexture(),
            vertexColors: true,
            transparent: true,
            opacity: 0.75,
            blending: THREE.NormalBlending,
            depthWrite: false
        });

        const particleMesh = new THREE.Points(geometry, material);
        scene.add(particleMesh);

        // Pointer Lerp Physics
        let targetX = 0;
        let targetY = 0;
        let mouseX = 0;
        let mouseY = 0;

        window.addEventListener('pointermove', function (e) {
            targetX = (e.clientX / window.innerWidth - 0.5) * 2;
            targetY = (e.clientY / window.innerHeight - 0.5) * 2;
        }, { passive: true });

        // Resize Listener
        window.addEventListener('resize', function () {
            if (!container) return;
            width = container.clientWidth;
            height = container.clientHeight;
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height);
        }, { passive: true });

        // Visibility Observer (Pause when off-screen for 0% GPU waste)
        let isVisible = true;
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                isVisible = entry.isIntersecting;
            });
        }, { threshold: 0.05 });
        observer.observe(container);

        // Render Loop
        let animationFrameId;
        let clock = new THREE.Clock();

        function animate() {
            animationFrameId = requestAnimationFrame(animate);

            if (!isVisible || prefersReducedMotion) return;

            const elapsedTime = clock.getElapsedTime();

            // Smooth mouse dampening
            mouseX += (targetX - mouseX) * 0.04;
            mouseY += (targetY - mouseY) * 0.04;

            camera.position.x = mouseX * 4;
            camera.position.y = -mouseY * 3;
            camera.lookAt(scene.position);

            const pos = geometry.attributes.position.array;
            for (let i = 0; i < particleCount; i++) {
                const i3 = i * 3;
                const spd = speeds[i];

                // Organic fluid wave motion
                pos[i3 + 1] = originalPositions[i3 + 1] + Math.sin(elapsedTime * spd + originalPositions[i3]) * 1.8;
                pos[i3] = originalPositions[i3] + Math.cos(elapsedTime * (spd * 0.7) + originalPositions[i3 + 1]) * 1.2;
            }
            geometry.attributes.position.needsUpdate = true;

            particleMesh.rotation.y = elapsedTime * 0.03;
            particleMesh.rotation.x = elapsedTime * 0.015;

            renderer.render(scene, camera);
        }

        if (prefersReducedMotion) {
            renderer.render(scene, camera);
        } else {
            animate();
        }

        // WebGL Context Lost Handling
        canvas.addEventListener('webglcontextlost', function (event) {
            event.preventDefault();
            cancelAnimationFrame(animationFrameId);
        }, false);
    }

    // -------------------------------------------------------------
    // 2. 21ST.DEV SPOTLIGHT PHYSICS ON BENTO CARDS
    // -------------------------------------------------------------
    function initSpotlights() {
        const cards = document.querySelectorAll('.bento-card');
        cards.forEach(card => {
            card.addEventListener('mousemove', function (e) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', x + 'px');
                card.style.setProperty('--mouse-y', y + 'px');
            }, { passive: true });
        });
    }

    // -------------------------------------------------------------
    // 3. LENIS SMOOTH SCROLL & GSAP SYNCHRONIZATION
    // -------------------------------------------------------------
    function initScrollPhysics() {
        if (prefersReducedMotion) return;

        let lenisInstance = null;

        if (typeof Lenis !== 'undefined') {
            lenisInstance = new Lenis({
                duration: 1.15,
                easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
                orientation: 'vertical',
                gestureOrientation: 'vertical',
                smoothWheel: true,
                touchMultiplier: 1.2
            });

            if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
                gsap.registerPlugin(ScrollTrigger);
                lenisInstance.on('scroll', ScrollTrigger.update);
                gsap.ticker.add((time) => {
                    lenisInstance.raf(time * 1000);
                });
                gsap.ticker.lagSmoothing(0);
            } else {
                function raf(time) {
                    lenisInstance.raf(time);
                    requestAnimationFrame(raf);
                }
                requestAnimationFrame(raf);
            }
        }

        // GSAP ScrollTrigger Micro-Animations
        if (typeof gsap !== 'undefined') {
            // Hero entrance
            gsap.from('.hero-content-overdrive', {
                opacity: 0,
                y: 28,
                duration: 0.9,
                ease: 'power3.out',
                delay: 0.1
            });

            gsap.from('.hero-showcase-card', {
                opacity: 0,
                scale: 0.96,
                y: 35,
                duration: 1,
                ease: 'power3.out',
                delay: 0.25
            });

            // Bento Cards reveal
            if (typeof ScrollTrigger !== 'undefined') {
                gsap.from('.bento-card', {
                    scrollTrigger: {
                        trigger: '.bento-matrix',
                        start: 'top 85%'
                    },
                    opacity: 0,
                    y: 30,
                    duration: 0.7,
                    stagger: 0.12,
                    ease: 'power3.out'
                });
            }
        }
    }

    // -------------------------------------------------------------
    // 4. BOOTSTRAP ORCHESTRATION
    // -------------------------------------------------------------
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initAmbientCanvas();
            initSpotlights();
            initScrollPhysics();
        });
    } else {
        initAmbientCanvas();
        initSpotlights();
        initScrollPhysics();
    }
})();
