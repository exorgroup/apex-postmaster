<?php

declare(strict_types=1);

class ContactController
{
    public static function submit(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('name', 'surname', 'email', 'phone', 'message')
            ->email('email')
            ->minLength('name', 2)
            ->minLength('surname', 2)
            ->minLength('message', 10);

        $validator->validate();

        $name = trim($input['name']);
        $surname = trim($input['surname']);
        $email = strtolower(trim($input['email']));
        $phone = trim($input['phone']);
        $company = isset($input['company']) ? trim($input['company']) : '';
        $message = trim($input['message']);

        // Save to database
        Database::insert('contact_submissions', [
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Send email notification
        $to = CONTACT_EMAIL;
        $subject = 'New Contact Form Submission - APEX PostMaster';

        $emailBody = "New contact form submission:\n\n";
        $emailBody .= "Name: $name $surname\n";
        $emailBody .= "Email: $email\n";
        $emailBody .= "Phone: $phone\n";
        if ($company) {
            $emailBody .= "Company: $company\n";
        }
        $emailBody .= "\nMessage:\n$message\n";
        $emailBody .= "\n---\n";
        $emailBody .= "Submitted at: " . date('Y-m-d H:i:s') . "\n";

        $headers = "From: noreply@" . parse_url(APP_URL, PHP_URL_HOST) . "\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        mail($to, $subject, $emailBody, $headers);

        Response::success(null, 'Message sent successfully');
    }
}
