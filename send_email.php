<?php
// Configurações de segurança
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Configurações do Mailtrap
$smtp_host = '';
$smtp_port = ;
$smtp_username = ';
$smtp_password = '72c29d8c4a616e';
$from_email = 'noreply@innovaplanning.com';
$from_name = 'Innova Planning - Site';
$to_email = 'comercial@innovaplanning.com';
$to_name = 'Comercial Innova Planning';

// Função para limpar e validar dados
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Verificar campos obrigatórios
$required_fields = ['name', 'email', 'message', 'math-captcha'];
$errors = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $errors[] = "Campo '$field' é obrigatório";
    }
}

// Verificar honeypot (campo anti-spam)
if (!empty($_POST['website'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Detecção de spam']);
    exit;
}

// Se há erros de validação
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Sanitizar dados
$name = sanitize_input($_POST['name']);
$email = sanitize_input($_POST['email']);
$company = sanitize_input($_POST['company'] ?? '');
$phone = sanitize_input($_POST['phone'] ?? '');
$service = sanitize_input($_POST['service'] ?? '');
$message = sanitize_input($_POST['message']);
$math_captcha = (int)$_POST['math-captcha'];

// Validar email
if (!validate_email($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

// Mapear serviços
$services_map = [
    'administrativo' => 'Administrativo',
    'contabil' => 'Contábil e Fiscal',
    'juridico' => 'Jurídico',
    'financeiro' => 'Financeiro',
    'ti' => 'TI & Sistemas',
    'projetos' => 'Projetos Customizados'
];

$service_label = $services_map[$service] ?? 'Não especificado';

// Preparar conteúdo do email
$subject = "Novo contato - Innova Planning";

$html_message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0AE18C; color: #000; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #0AE18C; }
        .footer { background-color: #333; color: #fff; padding: 15px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Novo Contato - Innova Planning</h1>
        </div>
        <div class='content'>
            <div class='field'>
                <span class='label'>Nome:</span> $name
            </div>
            <div class='field'>
                <span class='label'>Email:</span> $email
            </div>
            " . ($company ? "<div class='field'><span class='label'>Empresa:</span> $company</div>" : "") . "
            " . ($phone ? "<div class='field'><span class='label'>Telefone:</span> $phone</div>" : "") . "
            <div class='field'>
                <span class='label'>Serviço de Interesse:</span> $service_label
            </div>
            <div class='field'>
                <span class='label'>Mensagem:</span><br>
                " . nl2br($message) . "
            </div>
        </div>
        <div class='footer'>
            <p>Este email foi enviado através do formulário de contato do site Innova Planning</p>
            <p>Data/Hora: " . date('d/m/Y H:i:s') . "</p>
        </div>
    </div>
</body>
</html>
";

$text_message = "
NOVO CONTATO - INNOVA PLANNING
==============================

Nome: $name
Email: $email
" . ($company ? "Empresa: $company\n" : "") . "
" . ($phone ? "Telefone: $phone\n" : "") . "
Serviço de Interesse: $service_label

Mensagem:
$message

==============================
Enviado em: " . date('d/m/Y H:i:s') . "
";

// Função para enviar email via SMTP (implementação simples)
function send_smtp_email($to, $subject, $html_body, $text_body, $from_email, $from_name) {
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password;
    
    // Headers do email
    $headers = [
        "Subject: $subject",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"boundary-string\"",
        "From: $from_name <$from_email>",
        "Reply-To: $from_email",
        "X-Mailer: PHP/" . phpversion()
    ];
    
    // Corpo do email multipart
    $body = "--boundary-string\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $text_body . "\r\n\r\n";
    
    $body .= "--boundary-string\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html_body . "\r\n\r\n";
    
    $body .= "--boundary-string--\r\n";
    
    // Tentar envio via socket SMTP
    $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
    
    if (!$socket) {
        return false;
    }
    
    // Ler resposta inicial
    fgets($socket, 512);
    
    // Comandos SMTP
    $commands = [
        "EHLO localhost\r\n",
        "AUTH LOGIN\r\n",
        base64_encode($smtp_username) . "\r\n",
        base64_encode($smtp_password) . "\r\n",
        "MAIL FROM: <$from_email>\r\n",
        "RCPT TO: <$to>\r\n",
        "DATA\r\n"
    ];
    
    foreach ($commands as $command) {
        fputs($socket, $command);
        fgets($socket, 512);
    }
    
    // Enviar headers e corpo
    fputs($socket, implode("\r\n", $headers) . "\r\n\r\n");
    fputs($socket, $body);
    fputs($socket, "\r\n.\r\n");
    fgets($socket, 512);
    
    // Fechar conexão
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

// Tentar enviar o email
try {
    $email_sent = send_smtp_email($to_email, $subject, $html_message, $text_message, $email, $name);
    
    if ($email_sent) {
        // Email enviado com sucesso
        echo json_encode([
            'success' => true, 
            'message' => 'Mensagem enviada com sucesso! Entraremos em contato em breve.'
        ]);
    } else {
        // Erro no envio
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao enviar email. Tente novamente ou entre em contato por telefone.'
        ]);
    }
    
} catch (Exception $e) {
    // Log do erro (em produção, registrar em arquivo de log)
    error_log("Erro no envio de email: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor. Tente novamente mais tarde.'
    ]);
}
?> 