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

// お問い合わせ項目 → 收件人 对照表
$inquiry_to = [
    '調光器について'    => 'hamada@toyostar.co.jp',
    '照明器具について'  => 'hamada@toyostar.co.jp',
    '温熱器具について'  => 'hamada@toyostar.co.jp',
    'その他'           => 'hamada@toyostar.co.jp',
];

$to          = $inquiry_to[$_POST['inquiry_type']] ?? 'ryo@toyostar.co.jp';
$from_email  = 'noreply@toyostar.co.jp'; // 送信元メールアドレス
$site_name   = 'TOYOSTAR';                // サイト名
// ==========================================


// ============== 1. データを受け取る ==============
// $_POST は form method="post" で送信されたデータが入る
$company = isset($_POST['company']) ? $_POST['company'] : '';   // 貴社名（任意）
$name    = isset($_POST['name'])    ? $_POST['name']    : '';
$email   = isset($_POST['email'])   ? $_POST['email']   : '';
$tel     = isset($_POST['tel'])     ? $_POST['tel']     : '';   // 電話番号（任意）
$fax     = isset($_POST['fax'])     ? $_POST['fax']     : '';   // FAX（任意）
$inquiry_type = isset($_POST['inquiry_type']) ? $_POST['inquiry_type'] : '';
$message = isset($_POST['message']) ? $_POST['message'] : '';
$website = isset($_POST['website']) ? $_POST['website'] : '';   // ハニーポット


// メール内で空欄を見やすくするため、未入力は「（未入力）」に置換
$company_disp = $company !== '' ? $company : '（未入力）';
$tel_disp     = $tel     !== '' ? $tel     : '（未入力）';
$fax_disp     = $fax     !== '' ? $fax     : '（未入力）';

// ============== 2. バリデーション（入力チェック）==============
$errors = [];

// ご担当者名チェック（必須）
if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'ご担当者様が未入力、または長すぎます。';
}

// メールアドレスチェック（必須）
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'メールアドレスの形式が正しくありません。';
}

// お問い合わせ項目の選択チェック（必須）
if ($inquiry_type === '') {
    $errors[] = 'お問い合わせ項目を選択してください。';
}

// お問い合わせ内容チェック（必須）
if ($message === '' || mb_strlen($message) > 5000) {
    $errors[] = 'お問い合わせ内容が未入力、または長すぎます。';
}


// 電話番号チェック（任意・入力時のみ形式確認）
if ($tel !== '' && !preg_match('/^[0-9\-\+\s\(\)]+$/', $tel)) {
    $errors[] = '電話番号の形式が正しくありません。';
}

// FAX番号チェック（任意・入力時のみ形式確認）
if ($fax !== '' && !preg_match('/^[0-9\-\+\s\(\)]+$/', $fax)) {
    $errors[] = 'FAX番号の形式が正しくありません。';
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

// 改行コードを統一（CRLF）。mb_send_mailはLFだと改行が崩れることがある
$nl = "\r\n";

// お客様宛の自動返信メール本文
$customer_body  = "{$name} 様{$nl}{$nl}";
$customer_body .= "この度は、{$site_name}へお問い合わせいただき、{$nl}";
$customer_body .= "誠にありがとうございます。{$nl}{$nl}";
$customer_body .= "以下の内容でお問い合わせを受け付けました。{$nl}";
$customer_body .= "担当者より改めてご連絡いたしますので、今しばらくお待ちください。{$nl}{$nl}";
$customer_body .= "========================================{$nl}";
$customer_body .= "【貴社名】{$nl}{$company_disp}{$nl}{$nl}";
$customer_body .= "【ご担当者様】{$nl}{$name}{$nl}{$nl}";
$customer_body .= "【メールアドレス】{$nl}{$email}{$nl}{$nl}";
$customer_body .= "【電話番号】{$nl}{$tel_disp}{$nl}{$nl}";
$customer_body .= "【ＦＡＸ番号】{$nl}{$fax_disp}{$nl}{$nl}";
$customer_body .= "【お問い合わせ項目】{$nl}{$inquiry_type}{$nl}{$nl}";
$customer_body .= "【お問い合わせ内容】{$nl}{$message}{$nl}";
$customer_body .= "========================================{$nl}{$nl}";
$customer_body .= "送信日時：{$date}{$nl}{$nl}";
$customer_body .= "※このメールは自動送信です。ご返信いただいてもお答えできませんのでご了承ください。{$nl}{$nl}";
$customer_body .= "{$site_name}{$nl}";



// 会社宛の通知メール本文
$admin_body  = "ウェブサイトからお問い合わせがありました。{$nl}{$nl}";
$admin_body .= "========================================{$nl}";
$admin_body .= "【貴社名】{$nl}{$company_disp}{$nl}{$nl}";
$admin_body .= "【ご担当者様】{$nl}{$name}{$nl}{$nl}";
$admin_body .= "【メールアドレス】{$nl}{$email}{$nl}{$nl}";
$admin_body .= "【電話番号】{$nl}{$tel_disp}{$nl}{$nl}";
$admin_body .= "【ＦＡＸ番号】{$nl}{$fax_disp}{$nl}{$nl}";
$admin_body .= "【お問い合わせ項目】{$nl}{$inquiry_type}{$nl}{$nl}";
$admin_body .= "【お問い合わせ内容】{$nl}{$message}{$nl}";
$admin_body .= "========================================{$nl}{$nl}";
$admin_body .= "送信日時：{$date}{$nl}";
$admin_body .= "送信元IP：{$_SERVER['REMOTE_ADDR']}{$nl}";


// ============== 4. メール送信 ==============
// 文字エンコードを UTF-8 に設定（日本語メール対応）
mb_language('Japanese');
mb_internal_encoding('UTF-8');

// 件名
$admin_subject    = "[{$site_name}] ウェブサイトからのお問い合わせ";
$customer_subject = "【{$site_name}】お問い合わせありがとうございます";

// メール本文をBase64エンコード（日本語が確実に届く方法）
$admin_body_b64    = base64_encode($admin_body);
$customer_body_b64 = base64_encode($customer_body);

// ヘッダー（Content-Transfer-Encoding: base64 を必ず付ける）
$admin_headers  = "From: " . mb_encode_mimeheader($site_name) . " <{$from_email}>\r\n";
$admin_headers .= "Reply-To: {$email}\r\n";
$admin_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$admin_headers .= "Content-Transfer-Encoding: base64\r\n";
$admin_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$customer_headers  = "From: " . mb_encode_mimeheader($site_name) . " <{$from_email}>\r\n";
$customer_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$customer_headers .= "Content-Transfer-Encoding: base64\r\n";
$customer_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// 管理者宛てに送信（標準 mail() 関数を使用）
$admin_sent = mail($to, $admin_subject, $admin_body_b64, $admin_headers);

// お客様宛てに自動返信
$customer_sent = mail($email, $customer_subject, $customer_body_b64, $customer_headers);

// ============== 5. デバッグログ（問題特定用）==============
// サーバーに何が送られたか記録する（問題があればサーバー上からこのログを確認）
$log  = "========================================\r\n";
$log .= "送信日時: " . $date . "\r\n";
$log .= "TO: {$to}\r\n";
$log .= "FROM: {$from_email}\r\n";
$log .= "SUBJECT: {$admin_subject}\r\n";
$log .= "ADMIN_SENT: " . ($admin_sent ? 'SUCCESS' : 'FAILED') . "\r\n";
$log .= "CUSTOMER_SENT: " . ($customer_sent ? 'SUCCESS' : 'FAILED') . "\r\n";
$log .= "PHP_VERSION: " . phpversion() . "\r\n";
$log .= "----- メール本文（実際の値） -----\r\n";
$log .= $admin_body;
$log .= "\r\n----- Base64エンコード後 -----\r\n";
$log .= $admin_body_b64;
$log .= "\r\n========================================\r\n\r\n";

// ログファイルに追記
@file_put_contents(__DIR__ . '/contact_debug.log', $log, FILE_APPEND);

// 管理者宛メールが送信できれば成功とする
if ($admin_sent) {
    header('Location: contact_thanks.html');
} else {
    header('Location: contact_error.html');
}
exit;