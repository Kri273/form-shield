<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact Us</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #222;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 60px auto;
            padding: 20px;
        }

        .form-card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 10px;
            text-align: center;
        }


        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 16px;
            font-family: inherit;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #555;
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #222;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #444;
        }

        .honeypot {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="form-card">

        <h1>Contact Us</h1>
        
        <form action="logic.php" method="POST" autocomplete="on">
            <div class="form-group">
                <label for="name">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    maxlength="100"
                    autocomplete="name"
                    placeholder="Your name">
            </div>


            <div class="form-group">
                <label for="email">Email</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    maxlength="254"
                    autocomplete="email"
                    placeholder="you@example.com"
                >
            </div>


            <div class="form-group">
                <label for="phone">Phone number</label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    maxlength="30"
                    autocomplete="tel"
                    placeholder="+372 5123 4567"
                >
            </div>


            <!-- Message -->
            <div class="form-group">
                <label for="message">Message</label>

                <textarea
                    id="message"
                    name="message"
                    required
                    maxlength="5000"
                    placeholder="Write your message..."
                ></textarea>
            </div>


            <div class="honeypot" aria-hidden="true">
                <label for="website">Website</label>

                <input
                    type="text"
                    id="website"
                    name="website"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>


            <button type="submit">
                Send
            </button>

        </form>

    </div>

</div>

</body>
</html>
