<?php

// Formun POST metodu ile gönderilip gönderilmediğini kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 'karar_id' alanının dolu olup olmadığını kontrol et
    if (isset($_POST['karar_id']) && !empty(trim($_POST['karar_id']))) {
        
        // Gelen ID'yi al ve güvenlik için sadece rakamları bırak
        $kararId = filter_var(trim($_POST['karar_id']), FILTER_SANITIZE_NUMBER_INT);
        
        // ID'nin hala geçerli olup olmadığını kontrol et (boşluk veya geçersiz karakterlerden sonra)
        if (!empty($kararId)) {
            // Yargıtay'ın temel URL'sini tanımla
            $baseURL = 'https://karararama.yargitay.gov.tr/getDokuman?id=';
            
            // Tam URL'yi oluştur
            $hedefURL = $baseURL . $kararId;
            
            // Kullanıcıyı oluşturulan hedef URL'ye yönlendir
            header("Location: " . $hedefURL);
            
            // Yönlendirmeden sonra betiğin çalışmasını durdur
            exit();

        }
    }
}

// Eğer POST verisi yoksa veya karar_id boşsa, kullanıcıyı hata mesajıyla ana sayfaya geri yönlendir
header("Location: index.php?hata=gecersiz_id");
exit();

?>