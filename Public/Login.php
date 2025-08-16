<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login - Magline</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
   <link rel="icon" href="/MagLine/Public/Assets/favicon.png" type="image/png">
   <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

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

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, var(--dark-blue), var(--deep-indigo), var(--pink-glow));
      color: var(--text-light);
      overflow-x: hidden;
      min-height: 100vh;
      position: relative;
      scrollbar-width: none;
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

    .main-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      position: relative;
      z-index: 10;
    }

    .login-card {
      background: var(--glass-bg);
      backdrop-filter: blur(25px);
      border: 1.5px solid var(--glass-border);
      border-radius: 20px;
      padding: 3rem 2.8rem;
      max-width: 440px;
      width: 100%;
      position: relative;
      box-shadow: var(--shadow-glow);
      overflow: visible;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .login-card::before,
    .login-card::after {
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
    }

    .login-card::before {
      top: 0;
    }

    .login-card::after {
      bottom: 0;
    }

    .login-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 30px rgba(124, 77, 255, 0.7), 0 10px 40px rgba(209, 106, 255, 0.3);
      border-color: var(--pink-glow);
    }

    .login-card:hover::before,
    .login-card:hover::after {
      opacity: 1;
    }

    .login-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .login-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.4rem;
      font-weight: 700;
      color: var(--text-light);
      margin-bottom: 0.6rem;
      letter-spacing: 0.03em;
      position: relative;
      display: inline-block;
      text-shadow: 0 0 8px var(--pink-glow);
    }

    .login-title::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 70px;
      height: 3px;
      background: linear-gradient(90deg, transparent, var(--pink-glow), transparent);
      box-shadow: 0 0 15px var(--pink-glow);
      border-radius: 10px;
    }

    .role-badge {
      display: inline-block;
      padding: 0.45rem 1.3rem;
      background: rgba(209, 106, 255, 0.15);
      color: var(--pink-glow);
      border-radius: 22px;
      font-size: 0.9rem;
      font-weight: 600;
      text-transform: capitalize;
      border: 1.5px solid rgba(209, 106, 255, 0.3);
      backdrop-filter: blur(5px);
      user-select: none;
      box-shadow: 0 0 8px rgba(209, 106, 255, 0.3);
    }

    .form-group {
      margin-bottom: 1.5rem;
      position: relative;
    }

    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: var(--text-muted);
      font-size: 0.95rem;
      letter-spacing: 0.02em;
      transition: color 0.3s ease;
      user-select: none;
    }

    .form-input {
      width: 100%;
      padding: 1rem 1.5rem;
      background: var(--input-bg);
      border: 1.5px solid var(--input-border);
      border-radius: 10px;
      color: var(--text-light);
      font-size: 1rem;
      font-weight: 500;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      backdrop-filter: blur(5px);
      box-shadow: inset 0 0 8px rgba(124, 77, 255, 0.25);
    }

    .form-input:focus {
      outline: none;
      border-color: var(--pink-glow);
      background: var(--input-focus);
      box-shadow: 0 0 8px var(--pink-glow), inset 0 0 12px var(--pink-glow);
      color: var(--text-light);
    }

    .form-input::placeholder {
      color: var(--text-muted);
      opacity: 0.8;
    }

    .input-group {
      position: relative;
    }

    .input-icon {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1.1rem;
      transition: all 0.3s ease;
      pointer-events: none;
      filter: drop-shadow(0 0 1.5px rgba(209, 106, 255, 0.6));
    }

    .form-input:focus ~ .input-icon {
      color: var(--pink-glow);
      filter: drop-shadow(0 0 5px var(--pink-glow));
    }

    .btn-login {
      width: 100%;
      padding: 1.1rem 2rem;
      background: linear-gradient(135deg, var(--pink-glow), var(--blue-glow));
      border: none;
      border-radius: 10px;
      color: var(--text-light);
      font-size: 1rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
      box-shadow: 0 3px 15px rgba(209, 106, 255, 0.3);
      user-select: none;
      text-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
    }

    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
      transition: left 0.6s ease;
      pointer-events: none;
    }

    .btn-login:hover::before {
      left: 100%;
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(209, 106, 255, 0.5);
      background: linear-gradient(135deg, var(--pink-glow), var(--blue-glow));
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .login-footer {
      text-align: center;
      margin-top: 1.8rem;
    }

    .login-footer p {
      color: var(--text-muted);
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      user-select: none;
    }

    .signup-link {
      color: var(--pink-glow);
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      position: relative;
      font-size: 0.9rem;
      user-select: none;
      text-shadow: 0 0 5px var(--pink-glow);
    }

    .signup-link::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 1.5px;
      background: var(--pink-glow);
      box-shadow: 0 0 8px var(--pink-glow);
      transition: width 0.3s ease;
      border-radius: 2px;
    }

    .signup-link:hover {
      color: var(--text-light);
      text-shadow: 0 0 10px var(--pink-glow);
    }

    .signup-link:hover::after {
      width: 100%;
    }

    @media (max-width: 768px) {
      .login-card {
        padding: 2.5rem 2rem;
        margin: 1rem;
      }

      .login-title {
        font-size: 2rem;
      }
    }

.form-input:-webkit-autofill,
.form-input:-webkit-autofill:hover, 
.form-input:-webkit-autofill:focus {
  -webkit-text-fill-color: var(--text-light) !important;
  -webkit-box-shadow: 0 0 0px 1000px var(--input-bg) inset !important;
  transition: background-color 5000s ease-in-out 0s;
}

  </style>
</head>
<body>
  <canvas id="aiBackgroundCanvas"></canvas>

  <div class="main-container">
    <section class="login-card" role="main" aria-label="Login form">
      <header class="login-header">
        <h1 class="login-title">Welcome Back</h1>
        <div class="role-badge" aria-label="User role: <?= htmlspecialchars($_GET['role'] ?? 'candidate') ?>">
          <i class="fas <?= ($_GET['role'] ?? 'candidate') === 'recruiter' ? 'fa-user-shield' : 'fa-user-tie' ?>" style="margin-right: 0.4rem;" aria-hidden="true"></i>
          <?= htmlspecialchars($_GET['role'] ?? 'candidate') ?>
        </div>
      </header>

      <form action="login_process.php" method="POST" id="loginForm" novalidate>
        <input type="hidden" name="role" value="<?= htmlspecialchars($_GET['role'] ?? 'candidate') ?>" />

        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <div class="input-group">
            <input
              type="email"
              class="form-input"
              id="email"
              name="email"
              placeholder="Enter your email"
              required
              autocomplete="email"
              aria-required="true"
            />
            <i class="fas fa-envelope input-icon" aria-hidden="true"></i>
          </div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <div class="input-group">
            <input
              type="password"
              class="form-input"
              id="password"
              name="password"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
              aria-required="true"
            />
            <i class="fas fa-lock input-icon" aria-hidden="true"></i>
          </div>
        </div>

        <div class="form-group">
          <button type="submit" class="btn-login" aria-live="polite" aria-busy="false">
            <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;" aria-hidden="true"></i>
            Sign In
          </button>
        </div>
      </form>

      <footer class="login-footer">
        <p>Don't have an account?</p>
        <a href="signup.php?role=<?= htmlspecialchars($_GET['role'] ?? 'candidate') ?>" class="signup-link" role="link">
          Create Account
        </a>
      </footer>
    </section>
  </div>

  <script>
    (() => {
      const canvas = document.getElementById('aiBackgroundCanvas');
      const ctx = canvas.getContext('2d');
      let width, height;
      let particles = [];
      const particleCount = 70;
      const maxDistance = 150;

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
          this.vx = (Math.random() - 0.5) * 0.3;
          this.vy = (Math.random() - 0.5) * 0.3;
          this.size = Math.random() * 2 + 1;
          this.baseAlpha = Math.random() * 0.5 + 0.3;
          this.alpha = this.baseAlpha;
          this.pulseDirection = 1;
        }
        update() {
          this.x += this.vx;
          this.y += this.vy;

          if (this.x < 0 || this.x > width) this.vx *= -1;
          if (this.y < 0 || this.y > height) this.vy *= -1;

          this.alpha += 0.005 * this.pulseDirection;
          if (this.alpha > 1 || this.alpha < this.baseAlpha) this.pulseDirection *= -1;
        }
        draw() {
          ctx.beginPath();
          const gradient = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.size * 6);
          gradient.addColorStop(0, `rgba(209, 106, 255, ${this.alpha})`);
          gradient.addColorStop(1, 'rgba(209, 106, 255, 0)');
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
