<?php
/**
 * ============================================================
 *  TOYOSTAR お問い合わせフォーム処理
 * ============================================================
 *  処理の流れ:
 *    1. フォームから送信されたデータを受け取る
 *    2. 不正なデータ（空欄・スパム）をチェックする
 *    3. メール本文を組み立てて、指定の宛先に送信する
 *    4. 成功 → contact_thanks.html へリダイレクト
 *    5. 失敗 → contact_error.html へリダイレクト
 *
 *  ⚠ 設定が必要:
 *    - 下の「設定セクション」のメールアドレスを変更してください
 *    - サーバー上で mb_send_mail が使える必要があります
 *      （使えない場合はSMTP送信ライブラリPHPMailer等を使ってください）
 * ============================================================
 */

// ============== 設定セクション ==============
$to          = 'info@toyostar.co.jp';   // 受信先メールアドレス（ここを変更）
$from_email  = 'noreply@toyostar.co.jp'; // 送信元メールアドレス
$site_name   = 'TOYOSTAR';              // サイト名
// ==========================================


// ============== 1. データを受け取る ==============
// $_POST は form method="post" で送信されたデータが入る
$name    = isset($_POST['name'])    ? $_POST['name']    : '';
$email   = isset($_POST['email'])   ? $_POST['email']   : '';
$message = isset($_POST['message']) ? $_POST['message'] : '';
$website = isset($_POST['website']) ? $_POST['website'] : ''; // ハニーポット

// ============== 2. バリデーション（入力チェック）==============
$errors = [];

// お名前チェック
if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'お名前が未入力、または長すぎます。';
}

// メールアドレスチェック
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'メールアドレスの形式が正しくありません。';
}

// お問い合わせ内容チェック
if ($message === '' || mb_strlen($message) > 5000) {
    $errors[] = 'お問い合わせ内容が未入力、または長すぎます。';
}

// ハニーポットチェック（ここに値が入っていればBotと判断）
if ($website !== '') {
    $errors[] = '送信できませんでした。';
}

// エラーがあればエラー画面へ
if (!empty($errors)) {
    header('Location: contact_error.html');
    exit;
}


// ============== 3. メール本文を組み立てる ==============
// 日付を取得（例: 2026-06-29 14:32:10）
$date = date('Y-m-d H:i:s');

// お客様宛の自動返信メール本文
$customer_body = <<<EOT
{$name} 様

この度は、{$site_name}へお問い合わせいただき、
誠にありがとうございます。

以下の内容でお問い合わせを受け付けました。
担当者より改めてご連絡いたしますので、今しばらくお待ちください。

────────────────────────────
■ お名前
{$name}

■ メールアドレス
{$email}

■ お問い合わせ内容
{$message}
────────────────────────────

送信日時：{$date}

※このメールは自動送信です。ご返信いただいてもお答えできませんのでご了承ください。


{$site_name}
EOT;

// 会社宛の通知メール本文
$admin_body = <<<EOT
ウェブサイトからお問い合わせがありました。

■ お名前
{$name}

■ メールアドレス
{$email}

■ お問い合わせ内容
{$message}

────────────────────────────
送信日時：{$date}
送信元IP：{$_SERVER['REMOTE_ADDR']}
EOT;


// ============== 4. メール送信 ==============
// 文字エンコードを UTF-8 に設定（日本語メール対応）
mb_language('Japanese');
mb_internal_encoding('UTF-8');

// メールヘッダー
// "From:" を設定しないと、迷惑メール扱いされたり送信エラーになることがあります
$headers  = "From: " . mb_encode_mimeheader($site_name) . " <{$from_email}>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// 管理者宛てに送信
$admin_subject = "[{$site_name}] ウェブサイトからのお問い合わせ";
$admin_sent = mb_send_mail($to, $admin_subject, $admin_body, $headers);

// お客様宛てに自動返信（任意・失敗しても致命的ではない）
$customer_subject = "【{$site_name}】お問い合わせありがとうございます";
$customer_headers = "From: " . mb_encode_mimeheader($site_name) . " <{$from_email}>\r\n";
$customer_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$customer_sent = mb_send_mail($email, $customer_subject, $customer_body, $customer_headers);

// 管理者宛メールが送信できれば成功とする
if ($admin_sent) {
    header('Location: contact_thanks.html');
} else {
    header('Location: contact_error.html');
}
exit;