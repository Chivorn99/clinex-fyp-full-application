<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation to Join</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { width: 90%; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .button { display: inline-block; padding: 12px 25px; margin: 20px 0; font-size: 16px; color: #fff; background-color: #007bff; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome, {{ $user->name }}!</h1>
        <p>An account has been created for you. To get started, you need to set your password.</p>
        
        <p>
            <strong>Your login details:</strong><br>
            Email: <strong>{{ $user->email }}</strong><br>
            Temporary password: <strong>{{ $randomPassword }}</strong>
        </p>
        
        <p>Please click the button below to set your password and log in. This link is valid for 60 minutes.</p>
        
        <a href="{{ route('password.reset', ['token' => $token, 'email' => $user->email]) }}" class="button">
            Set Your Password
        </a>
        
        <p>If you did not expect to receive this email, you can safely ignore it.</p>
        <p>Thanks,<br>The Application Team</p>
    </div>
</body>
</html>
