<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Souq API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .info {
            color: #666;
            line-height: 1.6;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .endpoints {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Souq Ecommerce API</h1>

        <div class="info">
            <p>Welcome to the Souq API server. This is a Laravel REST API for the Souq ecommerce platform.</p>

            <h2>Quick Links</h2>
            <ul>
                <li><a href="https://github.com/Ali-Dakheel/Souq">GitHub Repository</a></li>
                <li><a href="/api/v1/">API Documentation</a></li>
                <li><a href="/admin">Admin Panel (Filament)</a></li>
            </ul>

            <h2>API Endpoints</h2>
            <div class="endpoints">
                POST   /api/v1/auth/register<br>
                POST   /api/v1/auth/login<br>
                POST   /api/v1/auth/logout<br>
                GET    /api/v1/auth/me<br>
                <br>
                GET    /api/v1/customers/profile<br>
                PUT    /api/v1/customers/profile<br>
                <br>
                GET    /api/v1/customers/addresses<br>
                POST   /api/v1/customers/addresses<br>
                PUT    /api/v1/customers/addresses/{id}<br>
                DELETE /api/v1/customers/addresses/{id}
            </div>

            <h2>Testing</h2>
            <p>Use Bruno to test API endpoints. Open the <code>bruno/</code> collection to get started.</p>
        </div>
    </div>
</body>
</html>
