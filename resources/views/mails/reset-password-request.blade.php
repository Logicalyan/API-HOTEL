<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Reset Password</title>
</head>
<body>
    <h1>{{ $data['heading'] }}</h1>
    <p>Hi {{ $data['name'] }},</p>
    <p>Email address: {{ $data['email'] }}</p>

    <p>Use the following code to reset your password: <strong>{{ $data['code'] }}</strong></p>
    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
</body>
</html>
