<?php
/**
 * core/email_helper.php
 * 
 * PHPMailer kütüphanesini kullanarak e-posta gönderimini yöneten
 * merkezi fonksiyonları içerir.
 */

// PHPMailer sınıflarını dahil et
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // SMTP sınıfını da dahil etmek en iyi pratiktir.

// Dosya yollarının her sunucuda doğru çalışmasını garanti altına al
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

/**
 * Projenin herhangi bir yerinden e-posta göndermek için kullanılan ana fonksiyon.
 *
 * @param string $to_email Alıcının e-posta adresi.
 * @param string $to_name Alıcının adı (isteğe bağlı).
 * @param string $subject E-postanın konusu.
 * @param string $body E-postanın HTML içeriği.
 * @return bool Gönderim başarılıysa true, değilse false.
 */
function sendEmail($to_email, $to_name, $subject, $body) {
    
    $mail = new PHPMailer(true);

    try {
        // --- SMTP Sunucu Ayarları ---
        
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Hata ayıklama için: 2 veya SMTP::DEBUG_SERVER detaylı logları gösterir.
        $mail->isSMTP();
        $mail->Host       = 'mailxx.liyakat.net';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xxxxxx@liyakat.net';
        $mail->Password   = 'xxxxx';
        
        // DİKKAT: Port 465 genellikle SMTPS (SSL) şifrelemesi kullanır.
        // Port 587 ise genellikle STARTTLS şifrelemesi kullanır.
        // Ayarlarınızda bir çelişki olabilir. Eğer 465 ile çalışmazsa,
        // aşağıdaki iki satırı değiştirerek deneyin:
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Port 465 için bu daha olasıdır.
        $mail->Port       = 465;

        // --- Gönderen ve Alıcı Bilgileri ---
        $mail->setFrom('dogrulama@liyakat.net', 'Karar Arama Platformu');
        $mail->addAddress($to_email, $to_name);

        // --- İçerik Ayarları ---
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; // Türkçe karakterler için kritik
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // HTML desteklemeyen istemciler için düz metin versiyonu

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Hata oluşursa, hatayı sunucu log dosyasına yaz.
        error_log("PHPMailer E-posta Gönderim Hatası: {$mail->ErrorInfo}");
        return false;
    }
}


/**
 * 2FA doğrulama kodu için standart HTML e-posta şablonu oluşturur.
 *
 * @param string $verification_code Kullanıcıya gönderilecek 6 haneli kod.
 * @return string E-postanın HTML içeriği.
 */
function getVerificationEmailTemplate($verification_code) {
    // E-posta içeriğini bir değişkene atayarak daha okunaklı hale getir
    $body = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 20px auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
            <div style="background-color: #1e3a8a; color: white; padding: 20px; text-align: center;">
                <h1 style="margin: 0; font-family: \'Roboto Slab\', serif;">Karar Arama Platformu</h1>
            </div>
            <div style="padding: 30px;">
                <h2 style="color: #1e3a8a;">Giriş Doğrulama Kodunuz</h2>
                <p>Merhaba,</p>
                <p>Hesabınıza giriş yapmak için aşağıdaki tek kullanımlık doğrulama kodunu kullanın. Bu kod <strong>10 dakika</strong> boyunca geçerlidir.</p>
                <p style="text-align: center; margin: 30px 0;">
                    <span style="display: inline-block; background-color: #f0f2f5; padding: 15px 25px; border-radius: 8px; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #333;">
                        ' . htmlspecialchars($verification_code) . '
                    </span>
                </p>
                <p>Eğer bu giriş denemesini siz yapmadıysanız, hesabınızın güvenliği için lütfen şifrenizi değiştirin.</p>
                <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                <p style="font-size: 12px; color: #888; text-align: center;">Bu e-posta, Karar Arama Platformu\'na yapılan bir giriş denemesi üzerine otomatik olarak gönderilmiştir.</p>
            </div>
        </div>
    ';
    return $body;
}

?>
