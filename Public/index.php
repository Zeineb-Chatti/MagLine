<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8" />
  <title>MagLine | AI-Powered Career Platform</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Magline connects top talent with dream opportunities through intelligent matching" />
  <meta name="theme-color" content="#0a0a2a" />
  <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

  <style>
    /* Include your original styles here, or link external CSS */
    :root {
      --dark-blue: #0a0a2a;
      --deep-indigo: #1a1a5a;
      --purple-glow: #7c4dff;
      --pink-glow: #d16aff;
      --blue-glow: #3b82f6;
      --text-light: #e0e7ff;
      --text-muted: #8a9eff;
      --glass-bg: rgba(10, 10, 42, 0.75);
      --glass-border: rgba(124, 77, 255, 0.25);
      --shadow-glow: 0 0 15px rgba(124, 77, 255, 0.45);
      --input-bg: rgba(10, 10, 42, 0.85);
      --input-border: rgba(124, 77, 255, 0.3);
      --input-focus: rgba(124, 77, 255, 0.25);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, var(--dark-blue), var(--deep-indigo), var(--pink-glow));
      color: var(--text-light);
      overflow-x: hidden;
      min-height: 100vh;
      position: relative;
      scrollbar-width: none;
      user-select: none;
    }
    body::-webkit-scrollbar {
      display: none;
    }

    #aiBackgroundCanvas {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      z-index: -10;
      background: transparent;
      display: block;
      will-change: transform;
    }

    /* Main Container */
    .main-container {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      position: relative;
      z-index: 10;
      text-align: center;
    }

    /* Logo Section */
    .center-logo {
      margin-bottom: 3rem;
      animation: logoEntrance 1.8s cubic-bezier(0.42, 0, 0.58, 1) forwards;
      opacity: 0;
      transform: translateY(-40px);
    }

    @keyframes logoEntrance {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .logo {
      font-family: 'Playfair Display', serif;
      font-size: 4rem;
      font-weight: 700;
      color: var(--text-light);
      margin-bottom: 0.6rem;
      letter-spacing: 0.03em;
      position: relative;
      display: inline-block;
      text-shadow:
        0 0 8px var(--pink-glow),
        0 0 20px var(--purple-glow);
      user-select: text;
    }

    .logo::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 120px;
      height: 3px;
      background: linear-gradient(90deg, transparent, var(--pink-glow), transparent);
      box-shadow: 0 0 15px var(--pink-glow);
      border-radius: 10px;
    }

    .subtitle {
      font-size: 1.2rem;
      color: var(--text-muted);
      margin-top: 1.5rem;
      font-weight: 300;
      letter-spacing: 1px;
      user-select: none;
    }

    /* Interactive Choice Cards */
    .choice-container {
      display: flex;
      gap: 3rem;
      margin-bottom: 3rem;
      flex-wrap: wrap;
      justify-content: center;
    }

    a.choice-card {
      position: relative;
      width: 320px;
      height: 420px;
      background: var(--glass-bg);
      border: 1.5px solid var(--glass-border);
      border-radius: 20px;
      backdrop-filter: blur(25px);
      overflow: hidden;
      cursor: pointer;
      transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      transform-style: preserve-3d;
      box-shadow: var(--shadow-glow);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 2.5rem 1.8rem;
      text-align: center;
      user-select: none;
      text-decoration: none;
      color: inherit;
    }

    a.choice-card:hover,
    a.choice-card:focus-visible {
      transform: translateY(-20px) scale(1.05);
      border-color: var(--pink-glow);
      box-shadow:
        0 0 35px rgba(124, 77, 255, 0.8),
        0 15px 50px rgba(209, 106, 255, 0.35);
      outline: none;
    }

    .choice-card::before,
    .choice-card::after {
      content: '';
      position: absolute;
      left: 10%;
      right: 10%;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--purple-glow), transparent);
      box-shadow: 0 0 15px var(--purple-glow);
      border-radius: 10px;
      opacity: 0.65;
      pointer-events: none;
      transition: opacity 0.3s ease;
      z-index: 1;
    }

    .choice-card::before {
      top: 0;
    }

    .choice-card::after {
      bottom: 0;
    }

    a.choice-card:hover::before,
    a.choice-card:hover::after {
      opacity: 1;
    }

    .card-content {
      position: relative;
      z-index: 2;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .card-icon {
      font-size: 4.5rem;
      margin-bottom: 1.5rem;
      background: linear-gradient(135deg, var(--pink-glow), var(--blue-glow));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: iconPulse 2.5s ease-in-out infinite;
      filter: drop-shadow(0 0 6px rgba(209, 106, 255, 0.7));
    }

    @keyframes iconPulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    .card-title {
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 1.2rem;
      color: var(--text-light);
      position: relative;
      user-select: text;
    }

    .card-title::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 2px;
      background: linear-gradient(90deg, var(--pink-glow), var(--blue-glow));
      border-radius: 2px;
    }

    .card-description {
      font-size: 1rem;
      color: var(--text-muted);
      line-height: 1.6;
      margin-bottom: 1.5rem;
      user-select: none;
    }

    .card-features {
      list-style: none;
      margin-top: auto;
      width: 100%;
      padding-left: 0;
    }

    .card-features li {
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
      position: relative;
      padding-left: 1.5rem;
      text-align: left;
      user-select: none;
    }

    .card-features li::before {
      content: '▹';
      position: absolute;
      left: 0;
      top: 0;
      color: var(--pink-glow);
      font-weight: 700;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .choice-container {
        flex-direction: column;
        gap: 2rem;
      }

      a.choice-card {
        width: 100%;
        max-width: 350px;
        height: 380px;
      }

      .logo {
        font-size: 2.5rem;
      }
    }
  </style>
</head>
<body>
  <canvas id="aiBackgroundCanvas" aria-hidden="true"></canvas>

  <main class="main-container" role="main" aria-label="Dashboard">
    <!-- Center Logo Section -->
    <section class="center-logo">
      <h1 class="logo">MagLine</h1>
      <p class="subtitle">Intelligent career matching powered by AI</p>
    </section>

    <!-- Interactive Choice Cards -->
    <section class="choice-container" role="list">
      <a href="Login.php?role=candidate" class="choice-card" role="listitem" tabindex="0" aria-label="For Talent: Discover opportunities tailored to your unique skills">
        <div class="card-content">
          <div class="card-icon"></div>
          <h3 class="card-title">For Talent</h3>
          <p class="card-description">Discover opportunities tailored to your unique skills</p>
          <ul class="card-features">
            <li>AI-powered job matching</li>
            <li>Instant application tracking</li>
            <li>Personalized career roadmap</li>
            <li>Smart skill analysis</li>
            <li>Geo-based recommendations</li>
          </ul>
        </div>
      </a>

      <a href="Login.php?role=recruiter" class="choice-card" role="listitem" tabindex="0" aria-label="For Recruiters: Find perfect candidates in half the time">
        <div class="card-content">
          <div class="card-icon"></div>
          <h3 class="card-title">For Recruiters</h3>
          <p class="card-description">Find perfect candidates in half the time</p>
          <ul class="card-features">
  <li>Streamlined job posting</li>
  <li>Live application tracking</li>
  <li>Smart candidate matching</li>
  <li>Application status dashboard</li>
  <li>Profile & offer management</li>
</ul>
        </div>
      </a>
    </section>
  </main>

  <script>
    // Animated Neural Network Particle Background
    (() => {
      const canvas = document.getElementById('aiBackgroundCanvas');
      const ctx = canvas.getContext('2d');
      let width, height;
      let particles = [];
      const particleCount = 75;
      const maxDistance = 140;

      function resize() {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width * devicePixelRatio;
        canvas.height = height * devicePixelRatio;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(devicePixelRatio, devicePixelRatio);
      }

      class Particle {
        constructor() {
          this.x = Math.random() * width;
          this.y = Math.random() * height;
          this.vx = (Math.random() - 0.5) * 0.25;
          this.vy = (Math.random() - 0.5) * 0.25;
          this.size = Math.random() * 1.8 + 1;
          this.baseAlpha = Math.random() * 0.5 + 0.25;
          this.alpha = this.baseAlpha;
          this.pulseDirection = 1;
          this.color = this.pickColor();
        }

        pickColor() {
          const colors = [
            'rgba(209, 106, 255, ALPHA)',   // pink-purple
            'rgba(59, 130, 246, ALPHA)',    // blue
            'rgba(124, 77, 255, ALPHA)'     // purple
          ];
          return colors[Math.floor(Math.random() * colors.length)];
        }

        update() {
          this.x += this.vx;
          this.y += this.vy;

          if (this.x < 0 || this.x > width) this.vx *= -1;
          if (this.y < 0 || this.y > height) this.vy *= -1;

          this.alpha += 0.006 * this.pulseDirection;
          if (this.alpha > 1 || this.alpha < this.baseAlpha) this.pulseDirection *= -1;
        }

        draw() {
          ctx.beginPath();
          const gradient = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.size * 5);
          gradient.addColorStop(0, this.color.replace('ALPHA', this.alpha));
          gradient.addColorStop(1, this.color.replace('ALPHA', '0'));
          ctx.fillStyle = gradient;
          ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
          ctx.fill();
        }
      }

      function connectParticles() {
        for (let i = 0; i < particles.length; i++) {
          for (let j = i + 1; j < particles.length; j++) {
            const p1 = particles[i];
            const p2 = particles[j];
            const dx = p1.x - p2.x;
            const dy = p1.y - p2.y;
            const dist = Math.sqrt(dx * dx + dy * dy);

            if (dist < maxDistance) {
              const alpha = 1 - dist / maxDistance;
              ctx.strokeStyle = `rgba(124, 77, 255, ${alpha * 0.4})`;
              ctx.lineWidth = 1.2;
              ctx.beginPath();
              ctx.moveTo(p1.x, p1.y);
              ctx.lineTo(p2.x, p2.y);
              ctx.stroke();
            }
          }
        }
      }

      function animate() {
        ctx.clearRect(0, 0, width, height);
        particles.forEach(p => {
          p.update();
          p.draw();
        });
        connectParticles();
        requestAnimationFrame(animate);
      }

      function initParticles() {
        particles = [];
        for (let i = 0; i < particleCount; i++) {
          particles.push(new Particle());
        }
      }

      window.addEventListener('resize', () => {
        resize();
        initParticles();
      });

      resize();
      initParticles();
      animate();
    })();
  </script>
</body>
</html>
