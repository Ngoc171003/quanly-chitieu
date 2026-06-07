<?php
/**
 * Mailer class - Gửi email cảnh báo ngân sách
 * Sử dụng PHPMailer hoặc mail() PHP native
 */
class Mailer {
    
    /**
     * Send email using PHPMailer if available, otherwise use PHP mail()
     */
    public static function send($to, $subject, $htmlBody) {
        // Try PHPMailer first
        $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($phpmailerPath) && defined('SMTP_HOST') && SMTP_HOST !== '') {
            return self::sendWithPHPMailer($to, $subject, $htmlBody);
        }
        
        // Fallback to PHP mail()
        return self::sendWithMail($to, $subject, $htmlBody);
    }
    
    /**
     * Send with PHPMailer (SMTP)
     */
    private static function sendWithPHPMailer($to, $subject, $htmlBody) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error (PHPMailer): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fallback: Send with PHP mail()
     */
    private static function sendWithMail($to, $subject, $htmlBody) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME) . " <noreply@chitieu.app>\r\n";
        
        return @mail($to, $subject, $htmlBody, $headers);
    }
    
    /**
     * Gửi cảnh báo SẮP vượt ngân sách (≥80%)
     */
    public static function sendBudgetWarning($userEmail, $userName, $budgetData) {
        $subject = "⚠️ Cảnh báo: Chi tiêu sắp vượt ngân sách tháng " . $budgetData['month'] . "/" . $budgetData['year'];
        
        $percentage = round($budgetData['percentage'], 1);
        $budget = number_format($budgetData['budget'], 0, ',', '.');
        $spent = number_format($budgetData['spent'], 0, ',', '.');
        $remaining = number_format($budgetData['remaining'], 0, ',', '.');
        $currency = $budgetData['currency'] ?? 'VNĐ';
        
        $html = self::getEmailTemplate(
            'warning',
            $userName,
            $subject,
            $percentage,
            $budget,
            $spent,
            $remaining,
            $currency,
            $budgetData['month'],
            $budgetData['year']
        );
        
        return self::send($userEmail, $subject, $html);
    }
    
    /**
     * Gửi cảnh báo ĐÃ vượt ngân sách (≥100%)
     */
    public static function sendBudgetExceeded($userEmail, $userName, $budgetData) {
        $subject = "🚨 Cảnh báo: Đã VƯỢT ngân sách tháng " . $budgetData['month'] . "/" . $budgetData['year'];
        
        $percentage = round($budgetData['percentage'], 1);
        $budget = number_format($budgetData['budget'], 0, ',', '.');
        $spent = number_format($budgetData['spent'], 0, ',', '.');
        $overflow = number_format($budgetData['overflow'], 0, ',', '.');
        $currency = $budgetData['currency'] ?? 'VNĐ';
        
        $html = self::getEmailTemplate(
            'exceeded',
            $userName,
            $subject,
            $percentage,
            $budget,
            $spent,
            '-' . $overflow,
            $currency,
            $budgetData['month'],
            $budgetData['year']
        );
        
        return self::send($userEmail, $subject, $html);
    }
    
    /**
     * Generate beautiful HTML email template
     */
    private static function getEmailTemplate($type, $userName, $title, $percentage, $budget, $spent, $remaining, $currency, $month, $year) {
        $isExceeded = ($type === 'exceeded');
        $primaryColor = $isExceeded ? '#ff4757' : '#ffa502';
        $bgGradient = $isExceeded 
            ? 'linear-gradient(135deg, #ff4757 0%, #ff6b81 100%)' 
            : 'linear-gradient(135deg, #ffa502 0%, #ffbe76 100%)';
        $icon = $isExceeded ? '🚨' : '⚠️';
        $statusText = $isExceeded ? 'ĐÃ VƯỢT NGÂN SÁCH' : 'SẮP VƯỢT NGÂN SÁCH';
        $messageText = $isExceeded 
            ? "Bạn đã chi tiêu vượt quá ngân sách đã đặt. Hãy xem xét cắt giảm chi tiêu trong thời gian còn lại của tháng."
            : "Chi tiêu của bạn đã đạt mức cảnh báo. Hãy cân nhắc trước khi chi tiêu thêm.";
        
        $progressWidth = min(100, $percentage);
        $progressColor = $isExceeded ? '#ff4757' : ($percentage >= 90 ? '#ffa502' : '#2ed573');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background-color:#f8f9fa;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <!-- Header -->
        <div style="background:{$bgGradient};border-radius:16px 16px 0 0;padding:32px 24px;text-align:center;">
            <div style="font-size:48px;margin-bottom:12px;">{$icon}</div>
            <h1 style="color:white;margin:0;font-size:22px;font-weight:700;">{$statusText}</h1>
            <p style="color:rgba(255,255,255,0.9);margin:8px 0 0;font-size:14px;">Tháng {$month}/{$year}</p>
        </div>
        
        <!-- Body -->
        <div style="background:white;padding:32px 24px;border-radius:0 0 16px 16px;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
            <p style="color:#2f3542;font-size:16px;margin:0 0 20px;">
                Xin chào <strong>{$userName}</strong>,
            </p>
            <p style="color:#57606f;font-size:14px;line-height:1.6;margin:0 0 24px;">
                {$messageText}
            </p>
            
            <!-- Progress Bar -->
            <div style="background:#f1f2f6;border-radius:10px;height:20px;margin:0 0 24px;overflow:hidden;">
                <div style="background:{$progressColor};height:100%;width:{$progressWidth}%;border-radius:10px;transition:width 0.3s;"></div>
            </div>
            
            <!-- Stats -->
            <div style="display:flex;gap:12px;margin:0 0 24px;">
                <div style="flex:1;background:#f8f9fa;border-radius:12px;padding:16px;text-align:center;">
                    <div style="color:#57606f;font-size:12px;margin-bottom:4px;">Ngân sách</div>
                    <div style="color:#2f3542;font-size:18px;font-weight:700;">{$budget} {$currency}</div>
                </div>
                <div style="flex:1;background:#f8f9fa;border-radius:12px;padding:16px;text-align:center;">
                    <div style="color:#57606f;font-size:12px;margin-bottom:4px;">Đã chi</div>
                    <div style="color:{$primaryColor};font-size:18px;font-weight:700;">{$spent} {$currency}</div>
                </div>
                <div style="flex:1;background:#f8f9fa;border-radius:12px;padding:16px;text-align:center;">
                    <div style="color:#57606f;font-size:12px;margin-bottom:4px;">Còn lại</div>
                    <div style="color:#2f3542;font-size:18px;font-weight:700;">{$remaining} {$currency}</div>
                </div>
            </div>
            
            <!-- Percentage -->
            <div style="text-align:center;margin:0 0 24px;">
                <span style="background:{$bgGradient};color:white;padding:8px 20px;border-radius:20px;font-size:14px;font-weight:600;">
                    Đã sử dụng {$percentage}% ngân sách
                </span>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align:center;margin:0 0 16px;">
                <a href="http://localhost/Myproject/budget.php" 
                   style="display:inline-block;background:linear-gradient(135deg,#4f8bff,#6db3ff);color:white;text-decoration:none;padding:14px 32px;border-radius:12px;font-weight:600;font-size:14px;">
                    📊 Xem Chi Tiết Ngân Sách
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="text-align:center;padding:20px;color:#a4b0be;font-size:12px;">
            <p style="margin:0;">Email tự động từ ứng dụng Chi Tiêu</p>
            <p style="margin:4px 0 0;">Bạn nhận email này vì đã thiết lập ngân sách tháng.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
