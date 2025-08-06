<?php
$role = $_GET['role'] ?? 'candidate';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Sign Up - Magline</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
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
      overflow: hidden;
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

    .signup-card {
      background: var(--glass-bg);
      backdrop-filter: blur(25px);
      border: 1.5px solid var(--glass-border);
      border-radius: 20px;
      padding: 3rem 2.8rem;
      max-width: 480px;
      width: 100%;
      box-shadow: var(--shadow-glow);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: visible;
    }

    .signup-card:hover {
      box-shadow: 0 0 30px rgba(124, 77, 255, 0.7), 0 10px 40px rgba(209, 106, 255, 0.3);
      border-color: var(--pink-glow);
      transform: translateY(-5px);
    }

    .signup-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .signup-title {
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

    .signup-title::after {
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
      margin-top: 0.5rem;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-family: 'Inter', sans-serif;
    }

    form {
      color: var(--text-light);
    }

    label {
      font-weight: 600;
      font-size: 0.95rem;
      margin-bottom: 0.5rem;
      display: block;
      user-select: none;
      color: var(--text-muted);
    }

    .input-group {
      position: relative;
      margin-bottom: 1.5rem;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 1rem 2.8rem 1rem 1.5rem;
      background: var(--input-bg);
      border: 1.5px solid var(--input-border);
      border-radius: 10px;
      color: var(--text-light);
      font-size: 1rem;
      font-weight: 500;
      transition: all 0.3s ease;
      backdrop-filter: blur(5px);
      box-shadow: inset 0 0 8px rgba(124, 77, 255, 0.25);
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus {
      outline: none;
      border-color: var(--pink-glow);
      background: var(--input-focus);
      box-shadow: 0 0 8px var(--pink-glow), inset 0 0 12px var(--pink-glow);
      color: var(--text-light);
    }

    input::placeholder {
      color: var(--text-muted);
      opacity: 0.8;
    }

    .input-icon {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1.2rem;
      pointer-events: none;
      filter: drop-shadow(0 0 1.5px rgba(209, 106, 255, 0.6));
      transition: color 0.3s ease;
    }
    input:focus + .input-icon {
      color: var(--pink-glow);
      filter: drop-shadow(0 0 5px var(--pink-glow));
    }

    button[type="submit"] {
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
      transition: all 0.3s ease;
      box-shadow: 0 3px 15px rgba(209, 106, 255, 0.3);
      user-select: none;
      text-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
    }
    button[type="submit"]:hover {
      box-shadow: 0 6px 20px rgba(209, 106, 255, 0.5);
      background: linear-gradient(135deg, var(--pink-glow), var(--blue-glow));
      transform: translateY(-2px);
    }
    button[type="submit"]:active {
      transform: translateY(0);
    }

    .signup-footer {
      text-align: center;
      margin-top: 1.8rem;
      color: var(--text-muted);
      font-size: 0.9rem;
      user-select: none;
    }
    .signup-footer a {
      color: var(--pink-glow);
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      text-shadow: 0 0 5px var(--pink-glow);
    }
    .signup-footer a:hover {
      color: var(--text-light);
      text-shadow: 0 0 10px var(--pink-glow);
    }

    @media (max-width: 768px) {
      .signup-card {
        padding: 2.5rem 2rem;
        margin: 1rem;
      }
      .signup-title {
        font-size: 2rem;
      }
    }
    
    /* Fix for autocomplete changing label colors */
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
    <section class="signup-card" role="main" aria-label="Sign Up form">
      <header class="signup-header">
        <h1 class="signup-title">Create Account</h1>
        <div class="role-badge" aria-label="User role: <?= htmlspecialchars($role) ?>">
          <i class="fas <?= $role === 'recruiter' ? 'fa-user-shield' : 'fa-user-tie' ?>" aria-hidden="true"></i>
          <?= htmlspecialchars($role) ?>
        </div>
      </header>

      <form action="signup_process.php" method="POST" onsubmit="return checkPasswords()">
        <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>" />

        <div class="input-group">
          <input type="text" id="name" name="name" placeholder="<?= $role === 'recruiter' ? 'Your Full Name' : 'Full Name' ?>" required />
          <i class="fas fa-user input-icon"></i>
        </div>

        <div class="input-group">
          <input type="email" id="email" name="email" placeholder="Email Address" required />
          <i class="fas fa-envelope input-icon"></i>
        </div>

        <div class="input-group">
          <input type="password" id="password" name="password" placeholder="Password" required minlength="6" />
          <i class="fas fa-lock input-icon"></i>
        </div>

        <div class="input-group">
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required minlength="6" />
          <i class="fas fa-lock input-icon"></i>
        </div>

        <button type="submit">Sign Up</button>
      </form>

      <p class="signup-footer">
        Already have an account? 
        <a href="login.php?role=<?= htmlspecialchars($role) ?>">Log in here</a>
      </p>
    </section>
  </div>

  <script>
    function checkPasswords() {
      const pwd = document.getElementById('password').value;
      const cpwd = document.getElementById('confirm_password').value;
      if (pwd !== cpwd) {
        alert('Passwords do not match!');
        return false;
      }
      return true;
    }

    // Animated Neural Network Particle Background
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