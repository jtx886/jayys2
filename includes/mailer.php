<?php
// SMTP邮件发送类 - 原生PHP实现，兼容所有版本

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

class Mailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from;
    private $fromName;
    private $socket;
    private $connected = false;
    
    public function __construct() {
        $config = get_config();
        $this->host = $config['smtp']['host'];
        $this->port = $config['smtp']['port'];
        $this->user = $config['smtp']['user'];
        $this->pass = $config['smtp']['pass'];
        $this->from = $config['smtp']['from'];
        $this->fromName = $config['smtp']['from_name'];
    }
    
    public function send($to, $subject, $body, $isHtml = true) {
        $this->connect();
        
        if (!$this->connected) {
            return false;
        }
        
        $this->command('EHLO ' . $this->host);
        $this->command('AUTH LOGIN');
        $this->command(base64_encode($this->user));
        $this->command(base64_encode($this->pass));
        $this->command('MAIL FROM:<' . $this->from . '>');
        $this->command('RCPT TO:<' . $to . '>');
        $this->command('DATA');
        
        $headers = '';
        $headers .= 'From: =?UTF-8?B?' . base64_encode($this->fromName) . '?=<' . $this->from . '>' . "\r\n";
        $headers .= 'To: =?UTF-8?B?' . base64_encode('用户') . '?=<' . $to . '>' . "\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        
        if ($isHtml) {
            $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        } else {
            $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        }
        
        $headers .= "\r\n";
        
        $this->socket_write($headers . $body . "\r\n.\r\n");
        $response = $this->socket_read();
        
        $this->command('QUIT');
        $this->close();
        
        return strpos($response, '250') !== false;
    }
    
    public function send_template($to, $subject, $templateName, $vars = array()) {
        $body = $this->get_email_template($templateName, $vars);
        return $this->send($to, $subject, $body, true);
    }
    
    private function get_email_template($name, $vars = array()) {
        $siteName = get_config()['site_name'];
        
        switch ($name) {
            case 'verification':
                $code = isset($vars['code']) ? $vars['code'] : '';
                return '
                <!DOCTYPE html>
                <html><head><meta charset="UTF-8"></head>
                <body style="margin:0;padding:0;background:#04070d;font-family:Arial,sans-serif;">
                <div style="max-width:600px;margin:0 auto;padding:40px 20px;">
                <div style="background:linear-gradient(135deg,#0d253f,#1a3a5c);border-radius:16px;padding:40px;text-align:center;">
                <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#01b4e4,#1f80e0);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:36px;">🎬</span></div>
                <h1 style="color:#01b4e4;font-size:28px;margin:0 0 10px 0;">' . $siteName . '</h1>
                <p style="color:#9aa0a6;font-size:14px;margin:0 0 30px 0;">邮箱验证码</p>
                <div style="background:rgba(1,180,228,0.1);border:2px dashed #01b4e4;border-radius:12px;padding:30px;margin-bottom:20px;">
                <span style="font-size:48px;font-weight:bold;color:#01b4e4;letter-spacing:8px;">' . $code . '</span></div>
                <p style="color:#9aa0a6;font-size:14px;margin:0;">您的验证码将在 10 分钟后过期</p>
                <p style="color:#555;font-size:12px;margin:20px 0 0 0;">这是一封自动发送的邮件，请勿直接回复</p>
                </div></div></body></html>';
                
            case 'banned':
                $username = isset($vars['username']) ? $vars['username'] : '';
                $reason = isset($vars['reason']) ? $vars['reason'] : '';
                $until = isset($vars['until']) ? $vars['until'] : '';
                return '
                <!DOCTYPE html>
                <html><head><meta charset="UTF-8"></head>
                <body style="margin:0;padding:0;background:#04070d;font-family:Arial,sans-serif;">
                <div style="max-width:600px;margin:0 auto;padding:40px 20px;">
                <div style="background:linear-gradient(135deg,#3d1a1a,#5c1f1f);border-radius:16px;padding:40px;text-align:center;">
                <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#e40101,#e01f1f);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:36px;">⚠️</span></div>
                <h1 style="color:#ff4444;font-size:28px;margin:0 0 10px 0;">账号封禁通知</h1>
                <div style="background:rgba(255,68,68,0.1);border-radius:12px;padding:30px;margin:20px 0;text-align:left;">
                <p style="color:#fff;font-size:16px;margin:0 0 15px 0;">亲爱的 <strong style="color:#01b4e4;">' . htmlspecialchars($username) . '</strong>：</p>
                <p style="color:#ccc;font-size:14px;line-height:1.6;margin:0 0 15px 0;">您的账号已被管理员封禁。</p>
                <p style="color:#ccc;font-size:14px;line-height:1.6;margin:0 0 10px 0;"><strong style="color:#fff;">封禁原因：</strong>' . htmlspecialchars($reason) . '</p>
                <p style="color:#ccc;font-size:14px;line-height:1.6;margin:0;"><strong style="color:#fff;">解封时间：</strong>' . htmlspecialchars($until) . '</p>
                </div>
                <p style="color:#999;font-size:12px;margin:20px 0 0 0;">如对封禁有疑问，请通过反馈功能联系管理员</p>
                </div></div></body></html>';
                
            case 'unbanned':
                $username = isset($vars['username']) ? $vars['username'] : '';
                return '
                <!DOCTYPE html>
                <html><head><meta charset="UTF-8"></head>
                <body style="margin:0;padding:0;background:#04070d;font-family:Arial,sans-serif;">
                <div style="max-width:600px;margin:0 auto;padding:40px 20px;">
                <div style="background:linear-gradient(135deg,#1a3d1a,#1f5c1f);border-radius:16px;padding:40px;text-align:center;">
                <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#01e468,#1fe080);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:36px;">✅</span></div>
                <h1 style="color:#01e468;font-size:28px;margin:0 0 10px 0;">账号解封通知</h1>
                <div style="background:rgba(1,228,104,0.1);border-radius:12px;padding:30px;margin:20px 0;text-align:left;">
                <p style="color:#fff;font-size:16px;margin:0 0 15px 0;">亲爱的 <strong style="color:#01b4e4;">' . htmlspecialchars($username) . '</strong>：</p>
                <p style="color:#ccc;font-size:14px;line-height:1.6;margin:0;">您的账号已被解封，现在可以正常使用' . $siteName . '了。</p>
                </div>
                </div></div></body></html>';
                
            case 'feedback_reply':
                $content = isset($vars['content']) ? $vars['content'] : '';
                return '
                <!DOCTYPE html>
                <html><head><meta charset="UTF-8"></head>
                <body style="margin:0;padding:0;background:#04070d;font-family:Arial,sans-serif;">
                <div style="max-width:600px;margin:0 auto;padding:40px 20px;">
                <div style="background:linear-gradient(135deg,#0d253f,#1a3a5c);border-radius:16px;padding:40px;text-align:center;">
                <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#01b4e4,#1f80e0);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:36px;">💬</span></div>
                <h1 style="color:#01b4e4;font-size:24px;margin:0 0 20px 0;">您的反馈有新回复</h1>
                <div style="background:rgba(1,180,228,0.1);border-radius:12px;padding:30px;margin:20px 0;text-align:left;">
                <p style="color:#ccc;font-size:14px;line-height:1.6;margin:0 0 10px 0;">管理员回复：</p>
                <p style="color:#fff;font-size:14px;line-height:1.6;margin:0;">' . htmlspecialchars($content) . '</p>
                </div>
                <p style="color:#999;font-size:12px;margin:20px 0 0 0;">请登录查看完整反馈内容</p>
                </div></div></body></html>';
                
            default:
                $content = isset($vars['content']) ? $vars['content'] : '';
                $title = isset($vars['title']) ? $vars['title'] : $subject;
                return '
                <!DOCTYPE html>
                <html><head><meta charset="UTF-8"></head>
                <body style="margin:0;padding:0;background:#04070d;font-family:Arial,sans-serif;">
                <div style="max-width:600px;margin:0 auto;padding:40px 20px;">
                <div style="background:linear-gradient(135deg,#0d253f,#1a3a5c);border-radius:16px;padding:40px;text-align:center;">
                <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#01b4e4,#1f80e0);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:36px;">' . (isset($vars['icon']) ? $vars['icon'] : '📢') . '</span></div>
                <h1 style="color:#01b4e4;font-size:24px;margin:0 0 20px 0;">' . htmlspecialchars($title) . '</h1>
                <div style="background:rgba(1,180,228,0.1);border-radius:12px;padding:30px;margin:20px 0;text-align:left;">
                <p style="color:#ccc;font-size:14px;line-height:1.8;margin:0;white-space:pre-wrap;">' . htmlspecialchars($content) . '</p>
                </div>
                </div></div></body></html>';
        }
    }
    
    private function connect() {
        $timeout = 10;
        if ($this->port == 465) {
            $this->socket = @fsockopen('ssl://' . $this->host, $this->port, $errno, $errstr, $timeout);
        } else {
            $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $timeout);
        }
        
        if (!$this->socket) {
            $this->connected = false;
            return;
        }
        
        $this->connected = true;
        $response = $this->socket_read();
        if (strpos($response, '220') !== false) {
            $this->connected = true;
        } else {
            $this->connected = false;
        }
    }
    
    private function command($command) {
        $this->socket_write($command . "\r\n");
        return $this->socket_read();
    }
    
    private function socket_write($data) {
        if (!$this->connected) {
            return;
        }
        fwrite($this->socket, $data);
        fflush($this->socket);
    }
    
    private function socket_read() {
        if (!$this->connected) {
            return '';
        }
        $response = '';
        while ($str = @fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
    
    private function close() {
        if ($this->connected && $this->socket) {
            @fclose($this->socket);
        }
        $this->connected = false;
    }
}
