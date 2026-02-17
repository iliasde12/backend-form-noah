<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../../vendor/autoload.php';

class mailer
{
    private $mail;

    public function __construct()
    {
        // Laad .env
        $this->loadEnv(__DIR__ . '/../.env');

        $this->mail = new PHPMailer(true);

        // SMTP configuratie
        $this->mail->isSMTP();
        $this->mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
        $this->mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = $_ENV['SMTP_PORT'] ?? 587;
        $this->mail->CharSet    = 'UTF-8';

        // ✅ Voor Gmail: disable SSL verification (alleen development!)
        // $this->mail->SMTPOptions = [
        //     'ssl' => [
        //         'verify_peer' => false,
        //         'verify_peer_name' => false,
        //         'allow_self_signed' => true
        //     ]
        // ];
    }

    private function loadEnv($path)
    {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }

    /**
     * Stuur intake email naar admin
     */
    public function sendIntakeToAdmin(array $data): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();

            // Afzender
            $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'];
            $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'Intake Formulier';
            $this->mail->setFrom($fromEmail, $fromName);

            // Ontvanger (admin)
            $adminEmail = $_ENV['ADMIN_EMAIL'] ?? null;
            $this->mail->addAddress($adminEmail);

            // Reply-to klant
            if (!empty($data['email'])) {
                $this->mail->addReplyTo($data['email'], $data['voornaam'] . ' ' . $data['achternaam']);
            }

            // HTML email
            $this->mail->isHTML(true);
            $this->mail->Subject = "🏋️ Nieuwe Intake: {$data['voornaam']} {$data['achternaam']}";

            $this->mail->Body = $this->getAdminEmailTemplate($data);
            $this->mail->AltBody = $this->getAdminEmailPlainText($data); // Fallback

            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Admin mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Stuur bevestiging naar klant
     */
    public function sendConfirmationToClient(array $data, ?string $calendlyUrl = null): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();

            // Afzender
            $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'];
            $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'Coaching Team';
            $this->mail->setFrom($fromEmail, $fromName);

            // Ontvanger (klant)
            $this->mail->addAddress($data['email'], $data['voornaam'] . ' ' . $data['achternaam']);

            // HTML email
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Bedankt voor je aanmelding! 🎯';

            $this->mail->Body = $this->getClientEmailTemplate($data, $calendlyUrl);
            $this->mail->AltBody = $this->getClientEmailPlainText($data, $calendlyUrl);

            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Client mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * HTML template voor admin
     */
    private function getAdminEmailTemplate(array $data): string
    {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                h1 { color: #ff6b6b; border-bottom: 3px solid #ff6b6b; padding-bottom: 10px; }
                h2 { color: #555; margin-top: 25px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .field { margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #ff6b6b; }
                .label { font-weight: bold; color: #666; }
                .value { color: #333; margin-top: 3px; }
                .highlight { background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .importance { font-size: 24px; color: #ff6b6b; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>🏋️ Nieuwe Intake Ontvangen</h1>
                
                <h2>📋 Persoonlijke Gegevens</h2>
                <div class='field'>
                    <div class='label'>Naam:</div>
                    <div class='value'>{$data['voornaam']} {$data['achternaam']}</div>
                </div>
                <div class='field'>
                    <div class='label'>Email:</div>
                    <div class='value'><a href='mailto:{$data['email']}'>{$data['email']}</a></div>
                </div>
                <div class='field'>
                    <div class='label'>Telefoon:</div>
                    <div class='value'>" . ($data['telefoon'] ?? 'Niet opgegeven') . "</div>
                </div>
                
                <h2>💪 Fysieke Gegevens</h2>
                <div class='field'>
                    <div class='label'>Leeftijd:</div>
                    <div class='value'>{$data['leeftijd']} jaar</div>
                </div>
                <div class='field'>
                    <div class='label'>Lengte / Gewicht:</div>
                    <div class='value'>{$data['lengte']} cm / {$data['gewicht']} kg</div>
                </div>
                <div class='field'>
                    <div class='label'>Beroep:</div>
                    <div class='value'>{$data['beroep']}</div>
                </div>
                <div class='field'>
                    <div class='label'>Blessures:</div>
                    <div class='value'>{$data['blessures']}</div>
                </div>
                
                <h2>🏃 Training & Voeding</h2>
                <div class='field'>
                    <div class='label'>Struggle:</div>
                    <div class='value'>{$data['struggle']}</div>
                </div>
                <div class='field'>
                    <div class='label'>Train frequentie:</div>
                    <div class='value'>{$data['trainFrequentie']}</div>
                </div>
                <div class='field'>
                    <div class='label'>Voeding aanpak:</div>
                    <div class='value'>{$data['voedingAanpak']}</div>
                </div>
                
                <h2>🎯 Doelen</h2>
                <div class='field'>
                    <div class='label'>Doelen:</div>
                    <div class='value'>{$data['doelen']}</div>
                </div>
                <div class='highlight'>
                    <div class='label'>Belangrijkheid:</div>
                    <div class='importance'>{$data['importance']}/10</div>
                </div>
                
                <h2>✅ Commitment</h2>
                <div class='field'>
                    <div class='label'>Bereid om te investeren:</div>
                    <div class='value'>" . ($data['actie'] === 'ja' ? '✅ Ja' : '❌ Nee') . "</div>
                </div>
                <div class='field'>
                    <div class='label'>Klaar om te starten:</div>
                    <div class='value'>" . ($data['startNu'] === 'ja' ? '✅ Ja, direct!' : '❌ Meer info gewenst') . "</div>
                </div>
                
                <div style='margin-top: 30px; padding: 15px; background: #f0f0f0; border-radius: 5px;'>
                    <small>Ontvangen op: " . date('d-m-Y H:i:s') . "</small>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Plain text versie voor admin
     */
    private function getAdminEmailPlainText(array $data): string
    {
        return "
Nieuwe Intake Ontvangen

PERSOONLIJKE GEGEVENS
Naam: {$data['voornaam']} {$data['achternaam']}
Email: {$data['email']}
Telefoon: " . ($data['telefoon'] ?? 'Niet opgegeven') . "

FYSIEKE GEGEVENS
Leeftijd: {$data['leeftijd']} jaar
Lengte: {$data['lengte']} cm
Gewicht: {$data['gewicht']} kg
Beroep: {$data['beroep']}
Blessures: {$data['blessures']}

TRAINING & VOEDING
Struggle: {$data['struggle']}
Train frequentie: {$data['trainFrequentie']}
Uit eten: {$data['uiteten']}
Voeding aanpak: {$data['voedingAanpak']}

DOELEN
Doelen: {$data['doelen']}
Belangrijkheid: {$data['importance']}/10

COMMITMENT
Bereid om te investeren: {$data['actie']}
Klaar om te starten: {$data['startNu']}

Ontvangen op: " . date('d-m-Y H:i:s');
    }

    /**
     * HTML template voor klant
     */
    private function getClientEmailTemplate(array $data, ?string $calendlyUrl): string
    {
        $calendlyButton = '';
        if ($calendlyUrl) {
            $calendlyButton = "
            <div style='background: #f9f9f9; padding: 25px; border-radius: 10px; margin: 25px 0; text-align: center;'>
                <h2 style='color: #ff6b6b; margin-top: 0;'>📅 Plan je kennismakingsgesprek</h2>
                <p>Klik hieronder om een moment te kiezen:</p>
                <a href='$calendlyUrl' style='
                    background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);
                    color: white;
                    padding: 15px 35px;
                    text-decoration: none;
                    border-radius: 10px;
                    font-weight: bold;
                    display: inline-block;
                    margin: 15px 0;
                '>Plan Afspraak In</a>
            </div>
            ";
        }

        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #ff6b6b;'>Bedankt {$data['voornaam']}!</h1>
                <p>Super dat je de intake hebt ingevuld! We hebben je gegevens ontvangen en nemen deze zo snel mogelijk door.</p>
                
                $calendlyButton
                
                <div style='background: #f0f0f0; padding: 20px; border-radius: 10px; margin: 25px 0;'>
                    <h3 style='margin-top: 0; color: #555;'>Wat gebeurt er nu?</h3>
                    <ul style='color: #666;'>
                        <li>We beoordelen je intake</li>
                        <li>Je ontvangt binnen 24-48 uur een reactie</li>
                        <li>We plannen een gratis kennismakingsgesprek</li>
                    </ul>
                </div>
                
                <p style='color: #666;'>Check je inbox (en spam folder) voor updates.</p>
                
                <p style='margin-top: 40px; color: #666;'>
                    Met sportieve groet,<br>
                    <strong style='color: #ff6b6b;'>Het Coaching Team</strong>
                </p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Plain text voor klant
     */
    private function getClientEmailPlainText(array $data): string
    {
      //  $calendlyText = $calendlyUrl ? "\n\nPlan je kennismakingsgesprek: $calendlyUrl\n" : '';

        return "
Bedankt {$data['voornaam']}!

We hebben je intake ontvangen en nemen deze zo snel mogelijk door.
Wat gebeurt er nu?
- We beoordelen je intake
- Je ontvangt binnen 24-48 uur een reactie
- We plannen een gratis kennismakingsgesprek

Check je inbox (en spam folder) voor updates.

Met sportieve groet,
Het Coaching Team
        ";
    }
}