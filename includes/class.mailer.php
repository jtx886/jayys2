<?php
// 邮件发送类 - 使用socket实现SMTP，兼容所有PHP版本
class Mailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from;
    private $fromName;
    private $timeout = 30;
    private $debug = false;

    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->user = SMTP_USER;
        $this->pass = SMTP_PASS;
        $this->from = SMTP_FROM;
        $this->fromName = SMTP_FROM_NAME;
    }

    public function send($to, $subject, $body, $isHtml = true) {
        // 使用fsockopen连接SMTP服务器（SSL）
        $host = 'ssl://' . $this->host;
        $fp = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$fp) {
            // 尝试非SSL方式
            $fp = @fsockopen($this->host, 25, $errno, $errstr, $this->timeout);
            if (!$fp) {
                return false;
            }
        }

        stream_set_timeout($fp, $this->timeout);
        $response = $this->readResponse($fp);
        
        if (strpos($response, '220') === false) {
            fclose($fp);
            return false;
        }

        // EHLO
        $this->sendCommand($fp, "EHLO " . $_SERVER['HTTP_HOST']);
        $response = $this->readResponse($fp);
        
        // 认证登录
        $this->sendCommand($fp, "AUTH LOGIN");
        $response = $this->readResponse($fp);
        
        $this->sendCommand($fp, base64_encode($this->user));
        $response = $this->readResponse($fp);
        
        $this->sendCommand($fp, base64_encode($this->pass));
        $response = $this->readResponse($fp);
        
        if (strpos($response, '235') === false) {
            fclose($fp);
            return false;
        }

        // MAIL FROM
        $this->sendCommand($fp, "MAIL FROM:<{$this->from}>");
        $response = $this->readResponse($fp);
        
        if (strpos($response, '250') === false) {
            fclose($fp);
            return false;
        }

        // RCPT TO
        $this->sendCommand($fp, "RCPT TO:<{$to}>");
        $response = $this->readResponse($fp);
        
        if (strpos($response, '250') === false) {
            fclose($fp);
            return false;
        }

        // DATA
        $this->sendCommand($fp, "DATA");
        $response = $this->readResponse($fp);
        
        if (strpos($response, '354') === false) {
            fclose($fp);
            return false;
        }

        // 邮件头和内容
        $boundary = md5(uniqid(time()));
        $headers = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->from}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "\r\n";

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode(strip_tags($body))) . "\r\n";
        
        if ($isHtml) {
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($body)) . "\r\n";
        }
        
        $message .= "--{$boundary}--\r\n.\r\n";

        $this->sendCommand($fp, $headers . $message);
        $response = $this->readResponse($fp);
        
        $success = strpos($response, '250') !== false;

        // QUIT
        $this->sendCommand($fp, "QUIT");
        fclose($fp);

        return $success;
    }

    private function sendCommand($fp, $command) {
        fputs($fp, $command . "\r\n");
    }

    private function readResponse($fp) {
        $response = '';
        while ($str = fgets($fp, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }

    // 发送验证码邮件
    public function sendVerificationCode($to, $code, $type = 'register') {
        $actionText = $type == 'register' ? '注册账号' : '重置密码';
        $subject = "Jay影视 - 邮箱验证码";
        
        $body = '<div style="max-width:600px;margin:0 auto;padding:30px 20px;font-family:Arial,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:16px;min-height:400px;">';
        $body .= '<div style="background:#fff;padding:40px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.1);">';
        $body .= '<div style="text-align:center;margin-bottom:30px;">';
        $body .= '<div style="display:inline-block;width:80px;height:80px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:50%;line-height:80px;font-size:36px;color:#fff;font-weight:bold;">J</div>';
        $body .= '<h1 style="color:#333;margin:20px 0 10px;font-size:28px;">Jay影视</h1>';
        $body .= '<p style="color:#666;font-size:14px;">您的邮箱验证码</p>';
        $body .= '</div>';
        $body .= '<div style="text-align:center;padding:30px;background:linear-gradient(135deg,#f5f7fa 0%,#e8ecf1 100%);border-radius:12px;margin-bottom:30px;">';
        $body .= '<p style="color:#666;margin:0 0 20px;font-size:15px;">您正在' . $actionText . '，以下是您的验证码：</p>';
        $body .= '<div style="display:inline-block;background:#fff;padding:20px 50px;border-radius:10px;box-shadow:0 4px 15px rgba(102,126,234,0.2);">';
        $body .= '<span style="font-size:42px;font-weight:bold;letter-spacing:8px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">' . $code . '</span>';
        $body .= '</div>';
        $body .= '<p style="color:#999;font-size:13px;margin-top:20px;">验证码有效期为10分钟</p>';
        $body .= '</div>';
        $body .= '<div style="background:#fff7ed;padding:15px 20px;border-radius:8px;border-left:4px solid #f59e0b;">';
        $body .= '<p style="color:#92400e;font-size:13px;margin:0;line-height:1.6;">⚠️ 如果这不是您本人的操作，请忽略此邮件，您的账号是安全的。切勿将验证码告诉他人！</p>';
        $body .= '</div>';
        $body .= '<p style="text-align:center;color:#999;font-size:12px;margin-top:30px;">© ' . date('Y') . ' Jay影视 版权所有</p>';
        $body .= '</div></div>';

        return $this->send($to, $subject, $body);
    }

    // 发送封禁通知
    public function sendBanNotice($to, $username, $banTime, $unbanTime, $reason = '') {
        $subject = "Jay影视 - 账号封禁通知";
        
        $banTimeStr = date('Y-m-d H:i:s', strtotime($banTime));
        $unbanTimeStr = $unbanTime ? date('Y-m-d H:i:s', strtotime($unbanTime)) : '永久';
        
        $body = '<div style="max-width:600px;margin:0 auto;padding:30px 20px;font-family:Arial,sans-serif;">';
        $body .= '<div style="background:#fff;padding:40px;border-radius:12px;border:2px solid #ef4444;box-shadow:0 10px 40px rgba(239,68,68,0.1);">';
        $body .= '<div style="text-align:center;margin-bottom:30px;">';
        $body .= '<div style="display:inline-block;width:80px;height:80px;background:#fee2e2;border-radius:50%;line-height:80px;font-size:40px;">🚫</div>';
        $body .= '<h1 style="color:#dc2626;margin:20px 0 10px;font-size:24px;">账号封禁通知</h1>';
        $body .= '</div>';
        $body .= '<div style="background:#fef2f2;padding:25px;border-radius:10px;">';
        $body .= '<p style="color:#333;font-size:15px;line-height:1.8;margin:0 0 15px;">尊敬的 <b style="color:#dc2626;">' . htmlspecialchars($username) . '</b>：</p>';
        $body .= '<p style="color:#333;font-size:15px;line-height:1.8;margin:0 0 20px;">很抱歉地通知您，您的Jay影视账号因违反网站规则已被封禁。</p>';
        $body .= '<table style="width:100%;border-collapse:collapse;">';
        $body .= '<tr><td style="padding:10px 15px;background:#fff;width:120px;color:#666;border-radius:6px 0 0 6px;">用户名</td><td style="padding:10px 15px;background:#fff;border-radius:0 6px 6px 0;font-weight:bold;">' . htmlspecialchars($username) . '</td></tr>';
        $body .= '<tr><td style="padding:10px 15px;background:#fff;width:120px;color:#666;">封禁时间</td><td style="padding:10px 15px;background:#fff;font-weight:bold;color:#f59e0b;">' . $banTimeStr . '</td></tr>';
        $body .= '<tr><td style="padding:10px 15px;background:#fff;width:120px;color:#666;border-radius:0 0 0 6px;">解封时间</td><td style="padding:10px 15px;background:#fff;border-radius:0 0 6px 0;font-weight:bold;color:' . ($unbanTime ? '#10b981' : '#dc2626') . ';">' . $unbanTimeStr . '</td></tr>';
        $body .= '</table>';
        if ($reason) {
            $body .= '<div style="margin-top:20px;padding:15px;background:#fff;border-radius:8px;border-left:4px solid #dc2626;">';
            $body .= '<p style="margin:0;color:#333;"><b>封禁原因：</b>' . htmlspecialchars($reason) . '</p>';
            $body .= '</div>';
        }
        $body .= '<p style="color:#666;font-size:14px;margin-top:25px;line-height:1.8;">如有疑问，请通过网站反馈功能联系管理员。</p>';
        $body .= '</div>';
        $body .= '<p style="text-align:center;color:#999;font-size:12px;margin-top:30px;">© ' . date('Y') . ' Jay影视 版权所有</p>';
        $body .= '</div></div>';

        return $this->send($to, $subject, $body);
    }

    // 发送自定义通知
    public function sendCustomNotice($to, $title, $content) {
        $subject = "Jay影视 - " . $title;
        
        $body = '<div style="max-width:600px;margin:0 auto;padding:30px 20px;font-family:Arial,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:16px;min-height:400px;">';
        $body .= '<div style="background:#fff;padding:40px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.1);">';
        $body .= '<div style="text-align:center;margin-bottom:30px;">';
        $body .= '<div style="display:inline-block;width:80px;height:80px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:50%;line-height:80px;font-size:36px;color:#fff;font-weight:bold;">J</div>';
        $body .= '<h1 style="color:#333;margin:20px 0 10px;font-size:24px;">' . htmlspecialchars($title) . '</h1>';
        $body .= '<p style="color:#666;font-size:14px;">来自 Jay影视 的通知</p>';
        $body .= '</div>';
        $body .= '<div style="padding:25px;background:#f9fafb;border-radius:10px;color:#333;font-size:15px;line-height:1.8;">';
        $body .= nl2br(htmlspecialchars($content));
        $body .= '</div>';
        $body .= '<p style="text-align:center;color:#999;font-size:12px;margin-top:30px;">© ' . date('Y') . ' Jay影视 版权所有</p>';
        $body .= '</div></div>';

        return $this->send($to, $subject, $body);
    }
}
?>
