<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LocNetServe v1.0.0</title>
  <style>
    /* Reset & base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #0d0d0f;
      color: #fff;
      text-align: center;
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* Neon style */
    .neon-text {
      font-size: 3rem;
      font-weight: bold;
      color: #ff2b2b;
      text-shadow: 0 0 10px #ff2b2b, 0 0 20px #ff2b2b, 0 0 40px #ff2b2b;
    }

    header {
      padding: 4rem 1rem;
      background: radial-gradient(circle, rgba(255,43,43,0.2) 0%, rgba(0,0,0,1) 100%);
    }

    header img {
      width: 140px;
      margin-bottom: 1rem;
      filter: drop-shadow(0 0 15px #ff2b2b);
    }

    .version {
      font-size: 1.2rem;
      color: #ff7777;
      margin-top: 0.5rem;
    }

    section {
      padding: 3rem 2rem;
      max-width: 900px;
      margin: auto;
    }

    h2 {
      color: #ff2b2b;
      text-shadow: 0 0 8px #ff2b2b;
      margin-bottom: 1rem;
    }

    p {
      margin-bottom: 1rem;
      font-size: 1.1rem;
    }

    .features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }

    .card {
      background: #1a1a1d;
      padding: 1.5rem;
      border-radius: 12px;
      border: 1px solid #ff2b2b44;
      box-shadow: 0 0 10px rgba(255, 43, 43, 0.3);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card:hover {
      transform: translateY(-6px);
      box-shadow: 0 0 25px rgba(255, 43, 43, 0.6);
    }

    footer {
      padding: 1rem;
      font-size: 0.9rem;
      color: #aaa;
      border-top: 1px solid #222;
      margin-top: 2rem;
    }
  </style>
</head>
<body>

  <header>
    <img src="http://localhost/dashboard/assets/logo1.png" alt="LocNetServe Logo">
	<h1 class="neon-text">LocNetServe</h1>
    <div class="version">v1.0.0</div>
    <p>Lightweight Local Web Server for Developers</p>
  </header>

  <section>
    <h2>About LocNetServe</h2>
    <p>
      LocNetServe (LNS) is a modern, lightweight, and customizable local server designed 
      for developers who need a fast and reliable environment for web projects. 
      It integrates Apache, MySQL, and PHP in one place with a clean dashboard and 
      support for AI-powered tools in future versions.
    </p>
  </section>

  <section>
    <h2>Key Features</h2>
    <div class="features">
      <div class="card">
        <h3>🚀 Fast Setup</h3>
        <p>Start Apache, MySQL, and PHP instantly with one click.</p>
      </div>
      <div class="card">
        <h3>📂 Easy Management</h3>
        <p>Manage projects, configs, and logs in one simple dashboard.</p>
      </div>
      <div class="card">
        <h3>🔒 Developer Friendly</h3>
        <p>Safe local environment optimized for testing and prototyping.</p>
      </div>
      <div class="card">
        <h3>🤖 AI Ready</h3>
        <p>Future-ready integration for AI tools like log analysis and code copilots.</p>
      </div>
    </div>
  </section>

  <footer>
    &copy; 2025 LocNetServe. Built with ❤️ for developers.
  </footer>

</body>
</html>
