<?php
/**
 * xsukax Local Network Call - Single PHP File with AES-256 Encryption
 */

define('DATA_DIR', __DIR__ . '/vpn_call_data');
define('ROOM_EXPIRY', 3600);

if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

function cleanupOldRooms() {
    $files = glob(DATA_DIR . '/*.json');
    $now = time();
    foreach ($files as $file) {
        if (($now - filemtime($file)) > ROOM_EXPIRY) {
            unlink($file);
        }
    }
}
cleanupOldRooms();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    $roomId = $_GET['room'] ?? '';
    
    if ($action === 'create_room') {
        $roomId = substr(md5(uniqid(rand(), true)), 0, 8);
        $roomData = [
            'id' => $roomId,
            'created' => time(),
            'lastUpdate' => time(),
            'host' => null,
            'guest' => null
        ];
        file_put_contents(DATA_DIR . "/{$roomId}.json", json_encode($roomData));
        echo json_encode(['success' => true, 'roomId' => $roomId]);
        exit;
    }
    
    if ($action === 'get_room_info') {
        $roomFile = DATA_DIR . "/{$roomId}.json";
        
        if (!file_exists($roomFile)) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }
        
        $roomData = json_decode(file_get_contents($roomFile), true);
        echo json_encode([
            'success' => true,
            'created' => $roomData['created'] ?? 0
        ]);
        exit;
    }
    
    if ($action === 'send_signal') {
        $input = json_decode(file_get_contents('php://input'), true);
        $roomFile = DATA_DIR . "/{$roomId}.json";
        
        if (!file_exists($roomFile)) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }
        
        $roomData = json_decode(file_get_contents($roomFile), true);
        $role = $input['role'] ?? 'host';
        $signalData = $input['signal'] ?? null;
        
        if ($role === 'host') {
            $roomData['host'] = $signalData;
        } else {
            $roomData['guest'] = $signalData;
        }
        
        $roomData['lastUpdate'] = time();
        file_put_contents($roomFile, json_encode($roomData));
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'get_signal') {
        $roomFile = DATA_DIR . "/{$roomId}.json";
        $role = $_GET['role'] ?? 'host';
        $timeout = 30;
        $start = time();
        
        while ((time() - $start) < $timeout) {
            if (!file_exists($roomFile)) {
                echo json_encode(['success' => false, 'error' => 'Room not found']);
                exit;
            }
            
            $roomData = json_decode(file_get_contents($roomFile), true);
            
            if ($role === 'host' && $roomData['guest'] !== null) {
                echo json_encode(['success' => true, 'signal' => $roomData['guest']]);
                exit;
            } elseif ($role === 'guest' && $roomData['host'] !== null) {
                echo json_encode(['success' => true, 'signal' => $roomData['host']]);
                exit;
            }
            
            usleep(500000);
        }
        
        echo json_encode(['success' => false, 'error' => 'Timeout']);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">
    <title>xsukax Local Network Call</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        :root {
            --safe-area-inset-top: env(safe-area-inset-top);
            --safe-area-inset-bottom: env(safe-area-inset-bottom);
            --safe-area-inset-left: env(safe-area-inset-left);
            --safe-area-inset-right: env(safe-area-inset-right);
        }
        
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif; background: #ffffff; color: #24292f; overflow-x: hidden; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .card { background: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; padding: 24px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(27,31,36,0.12); }
        .header { border-bottom: 1px solid #d0d7de; padding-bottom: 16px; margin-bottom: 24px; }
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: 1px solid; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; user-select: none; }
        .btn-primary { background: #2da44e; color: #ffffff; border-color: rgba(27,31,36,0.15); }
        .btn-primary:hover { background: #2c974b; }
        .btn-primary:active { transform: scale(0.95); }
        .btn-primary:disabled { background: #94d3a2; cursor: not-allowed; }
        .btn-secondary { background: #f6f8fa; color: #24292f; border-color: rgba(27,31,36,0.15); }
        .btn-secondary:hover { background: #f3f4f6; }
        .btn-danger { background: #cf222e; color: #ffffff; border-color: rgba(27,31,36,0.15); }
        .btn-danger:hover { background: #a40e26; }
        .btn-large { padding: 12px 20px; font-size: 16px; width: 100%; }
        .input { width: 100%; padding: 8px 12px; border: 1px solid #d0d7de; border-radius: 6px; font-size: 14px; background: #ffffff; color: #24292f; transition: border-color 0.2s; }
        .input:focus { outline: none; border-color: #0969da; box-shadow: 0 0 0 3px rgba(9,105,218,0.1); }
        .label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #24292f; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #dafbe1; color: #0f5323; }
        .badge-warning { background: #fff8c5; color: #7d4e00; }
        .badge-default { background: #eaeef2; color: #57606a; }
        
        #callScreen { position: fixed; inset: 0; background: #000; z-index: 9999; display: none; }
        #callScreen.active { display: block; }
        
        .video-wrapper { position: relative; width: 100%; height: 100vh; height: 100dvh; background: #000; overflow: hidden; }
        
        #remoteVideo { width: 100%; height: 100%; object-fit: cover; }
        
        #localVideo { position: absolute; width: 120px; height: 160px; border-radius: 12px; border: 2px solid rgba(255,255,255,0.5); box-shadow: 0 8px 24px rgba(0,0,0,0.4); object-fit: cover; cursor: move; touch-action: none; transition: opacity 0.3s; z-index: 100; }
        #localVideo.hidden-preview { opacity: 0; pointer-events: none; }
        
        .call-overlay { position: absolute; inset: 0; pointer-events: none; z-index: 50; }
        
        .call-header { position: absolute; top: 0; left: 0; right: 0; padding: 20px; padding-top: max(20px, var(--safe-area-inset-top)); padding-left: max(20px, var(--safe-area-inset-left)); padding-right: max(20px, var(--safe-area-inset-right)); background: linear-gradient(to bottom, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 100%); pointer-events: auto; }
        
        .call-info { color: white; text-align: center; }
        .call-info h2 { font-size: 18px; font-weight: 600; margin-bottom: 4px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .call-info p { font-size: 14px; opacity: 0.9; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        
        .call-controls { position: absolute; bottom: 0; left: 0; right: 0; padding: 32px 20px; padding-bottom: max(32px, calc(var(--safe-area-inset-bottom) + 70px)); padding-left: max(20px, var(--safe-area-inset-left)); padding-right: max(20px, var(--safe-area-inset-right)); background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%); pointer-events: auto; }
        
        .control-buttons { display: flex; justify-content: center; align-items: center; gap: 20px; flex-wrap: wrap; }
        
        .control-btn { width: 56px; height: 56px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; font-size: 24px; user-select: none; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .control-btn:active { transform: scale(0.9); }
        
        .control-btn.video { background: rgba(255,255,255,0.2); color: white; }
        .control-btn.video.off { background: rgba(255,255,255,0.9); color: #24292f; }
        
        .control-btn.audio { background: rgba(255,255,255,0.2); color: white; }
        .control-btn.audio.off { background: rgba(255,255,255,0.9); color: #24292f; }
        
        .control-btn.hangup { background: #ef4444; color: white; width: 64px; height: 64px; font-size: 28px; }
        .control-btn.hangup:hover { background: #dc2626; }
        
        .control-btn.fullscreen { background: rgba(255,255,255,0.2); color: white; }
        .control-btn.fullscreen:hover { background: rgba(255,255,255,0.3); }
        
        .control-btn.hide-self { background: rgba(255,255,255,0.2); color: white; font-size: 20px; }
        .control-btn.hide-self.active { background: rgba(255,255,255,0.9); color: #24292f; }
        
        .control-btn.audio-only { background: rgba(255,255,255,0.2); color: white; font-size: 20px; }
        .control-btn.audio-only.active { background: #10b981; color: white; }
        
        @media (max-width: 768px) {
            #localVideo { width: 90px; height: 120px; }
            .control-btn { width: 52px; height: 52px; font-size: 22px; }
            .control-btn.hangup { width: 60px; height: 60px; font-size: 26px; }
            .call-controls { padding: 24px 16px; padding-bottom: max(70px, calc(var(--safe-area-inset-bottom) + 85px)); }
            .control-buttons { gap: 16px; }
            .call-header { padding: 16px; padding-top: max(16px, var(--safe-area-inset-top)); }
            .call-info h2 { font-size: 16px; }
            .call-info p { font-size: 13px; }
        }
        
        @media (max-width: 480px) {
            #localVideo { width: 80px; height: 106px; }
            .control-btn { width: 48px; height: 48px; font-size: 20px; }
            .control-btn.hangup { width: 56px; height: 56px; font-size: 24px; }
            .control-buttons { gap: 12px; }
            .call-controls { padding: 20px 12px; padding-bottom: max(75px, calc(var(--safe-area-inset-bottom) + 90px)); }
        }
        
        @media (max-width: 896px) and (orientation: landscape) {
            .call-controls { padding: 16px 20px; padding-bottom: max(50px, calc(var(--safe-area-inset-bottom) + 65px)); }
            .control-buttons { gap: 12px; }
            .call-header { padding: 12px 20px; }
        }
        
        .video-wrapper:-webkit-full-screen { width: 100vw; height: 100vh; }
        .video-wrapper:-moz-full-screen { width: 100vw; height: 100vh; }
        .video-wrapper:fullscreen { width: 100vw; height: 100vh; }
        
        .audio-only-mode { display: none; position: absolute; inset: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); align-items: center; justify-content: center; flex-direction: column; color: white; z-index: 10; }
        .audio-only-mode.active { display: flex; }
        .audio-only-mode .avatar { width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 48px; margin-bottom: 24px; border: 4px solid rgba(255,255,255,0.3); }
        .audio-only-mode h3 { font-size: 24px; font-weight: 600; margin-bottom: 8px; }
        .audio-only-mode p { font-size: 16px; opacity: 0.9; }
        
        .notification { position: fixed; top: 20px; right: 20px; background: #ffffff; padding: 16px; border-radius: 6px; box-shadow: 0 8px 24px rgba(27,31,36,0.15); border: 1px solid #d0d7de; z-index: 10000; max-width: 400px; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .notification.success { border-left: 3px solid #2da44e; }
        .notification.error { border-left: 3px solid #cf222e; }
        .notification.info { border-left: 3px solid #0969da; }
        .hidden { display: none !important; }
        .url-display { background: #f6f8fa; border: 1px solid #d0d7de; border-radius: 6px; padding: 12px; font-family: ui-monospace, monospace; font-size: 12px; word-break: break-all; color: #0969da; cursor: pointer; }
        .url-display:hover { background: #eaeef2; }
        .success-box { background: #dafbe1; border: 1px solid #a2dfb1; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .info-box { background: #ddf4ff; border: 1px solid #9cd7ff; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .warning-box { background: #fff8c5; border: 1px solid #f4e184; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
        .status-offline { background: #d1d5db; }
        .status-connecting { background: #f59e0b; animation: pulse 2s infinite; }
        .status-online { background: #2da44e; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .help-text { font-size: 13px; color: #57606a; margin-top: 8px; }
        select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2324292f' d='M6 9L1 4h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 8px center; padding-right: 32px; }
    </style>
</head>
<body>
    <!-- Setup Screen -->
    <div id="setupScreen" class="container">
        <div class="header">
            <h1 style="font-size: 32px; font-weight: 600; margin-bottom: 8px;">🔐 xsukax Local Network Call</h1>
            <p style="color: #57606a; font-size: 14px;">AES-256 encrypted signaling • DTLS-SRTP media encryption • Zero-knowledge architecture</p>
        </div>

        <div class="info-box">
            <p style="font-weight: 600; color: #0a3069; margin-bottom: 8px;">🔒 Security Features</p>
            <p style="color: #0a3069; font-size: 14px;">End-to-end encrypted Audio/video calling with AES-256 (Works With OpenVPN and Tailscale)</p>
        </div>

        <div id="startSection" class="card">
            <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Start New Call</h2>

            <div style="margin-bottom: 16px;">
                <label class="label">Room Password (AES-256)</label>
                <input type="password" id="roomPasswordInput" class="input" placeholder="Enter a secure password">
                <p class="help-text">🔐 This password encrypts all signaling data. Share it separately from the room link.</p>
            </div>

            <div style="margin-bottom: 16px;">
                <label class="label">Video Quality</label>
                <select id="videoQuality" class="input">
                    <option value="hd">HD (1280x720)</option>
                    <option value="fullhd">Full HD (1920x1080)</option>
                    <option value="sd">SD (640x480)</option>
                </select>
            </div>

            <button id="createRoomBtn" class="btn btn-primary btn-large">🚀 Create Encrypted Room</button>
        </div>

        <div id="roomSection" class="card hidden">
            <div class="success-box">
                <p style="font-weight: 600; color: #0f5323; margin-bottom: 8px;">✓ Encrypted Room Created!</p>
                <p style="color: #0f5323; font-size: 14px;">Share the link and password separately for maximum security.</p>
            </div>

            <label class="label">Room Link</label>
            <div class="url-display" id="roomUrl">Generating...</div>
            
            <button id="copyRoomUrlBtn" class="btn btn-primary" style="width: 100%; margin-bottom: 8px;">📋 Copy Room Link</button>

            <div class="warning-box" style="margin-top: 16px;">
                <p style="font-weight: 600; color: #7d4e00; margin-bottom: 4px;">⚠️ Security Notice</p>
                <p style="color: #7d4e00; font-size: 13px;">Send the password through a different channel (SMS, Signal, etc.) for best security.</p>
            </div>
        </div>

        <div id="joinSection" class="card hidden">
            <div class="success-box">
                <p style="font-weight: 600; color: #0f5323; margin-bottom: 8px;">🎉 Room Found!</p>
                <p style="color: #0f5323; font-size: 14px;">Enter the password to join the encrypted call.</p>
            </div>

            <div style="margin-bottom: 16px;">
                <label class="label">Enter Room Password</label>
                <input type="password" id="joinPasswordInput" class="input" placeholder="Enter the password from host" autofocus>
                <p class="help-text">🔐 Required to decrypt the encrypted connection data</p>
            </div>

            <button id="joinRoomBtn" class="btn btn-primary btn-large">📞 Decrypt & Join Call</button>
        </div>

        <div class="card">
            <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Status</h2>
            <div style="display: flex; align-items: center; padding: 12px; background: #f6f8fa; border-radius: 6px;">
                <span class="status-dot status-offline" id="statusDot"></span>
                <span id="statusText" style="font-size: 14px; font-weight: 600;">Ready</span>
                <span class="badge badge-default" id="statusBadge" style="margin-left: auto;">Offline</span>
            </div>
            <p id="statusDetails" class="help-text" style="margin-top: 8px;">Create or join an encrypted room</p>
        </div>
    </div>

    <!-- Professional Call Screen -->
    <div id="callScreen">
        <div class="video-wrapper" id="videoWrapper">
            <video id="remoteVideo" autoplay playsinline></video>
            <video id="localVideo" autoplay playsinline muted></video>
            
            <div class="audio-only-mode" id="audioOnlyOverlay">
                <div class="avatar">🎵</div>
                <h3>Audio Only</h3>
                <p>Video turned off</p>
            </div>
            
            <div class="call-overlay">
                <div class="call-header">
                    <div class="call-info">
                        <h2 id="callTitle">🔐 Encrypted Call</h2>
                        <p id="callSubtitle">AES-256 + DTLS-SRTP encryption</p>
                    </div>
                </div>
                
                <div class="call-controls">
                    <div class="control-buttons">
                        <button class="control-btn video" id="toggleVideoBtn" title="Toggle camera">📹</button>
                        <button class="control-btn audio" id="toggleAudioBtn" title="Toggle microphone">🎤</button>
                        <button class="control-btn hide-self" id="hideSelfBtn" title="Hide my video">👁️</button>
                        <button class="control-btn audio-only" id="audioOnlyBtn" title="Audio only mode">🎵</button>
                        <button class="control-btn hangup" id="hangupBtn" title="End call">📞</button>
                        <button class="control-btn fullscreen" id="fullscreenBtn" title="Fullscreen">⛶</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="notificationContainer"></div>

    <script>
        const state = {
            localStream: null,
            peerConnection: null,
            isAudioEnabled: true,
            isVideoEnabled: true,
            isLocalVideoHidden: false,
            isAudioOnlyMode: false,
            role: null,
            roomId: null,
            roomPassword: null,
            dragData: { isDragging: false, startX: 0, startY: 0, initialX: 0, initialY: 0 }
        };

        const rtcConfig = {
            iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
            iceTransportPolicy: 'all',
            bundlePolicy: 'max-bundle',
            rtcpMuxPolicy: 'require'
        };

        // AES-256 ENCRYPTION
        async function deriveKey(password, salt) {
            const encoder = new TextEncoder();
            const passwordBuffer = encoder.encode(password);
            
            const keyMaterial = await crypto.subtle.importKey(
                'raw',
                passwordBuffer,
                'PBKDF2',
                false,
                ['deriveBits', 'deriveKey']
            );
            
            return await crypto.subtle.deriveKey(
                {
                    name: 'PBKDF2',
                    salt: salt,
                    iterations: 100000,
                    hash: 'SHA-256'
                },
                keyMaterial,
                { name: 'AES-GCM', length: 256 },
                false,
                ['encrypt', 'decrypt']
            );
        }
        
        async function encryptData(data, password) {
            const encoder = new TextEncoder();
            const dataBuffer = encoder.encode(JSON.stringify(data));
            
            const salt = crypto.getRandomValues(new Uint8Array(16));
            const iv = crypto.getRandomValues(new Uint8Array(12));
            
            const key = await deriveKey(password, salt);
            
            const encryptedBuffer = await crypto.subtle.encrypt(
                { name: 'AES-GCM', iv: iv },
                key,
                dataBuffer
            );
            
            const encryptedArray = new Uint8Array(encryptedBuffer);
            const combined = new Uint8Array(salt.length + iv.length + encryptedArray.length);
            combined.set(salt, 0);
            combined.set(iv, salt.length);
            combined.set(encryptedArray, salt.length + iv.length);
            
            return btoa(String.fromCharCode.apply(null, combined));
        }
        
        async function decryptData(encryptedBase64, password) {
            try {
                const combined = new Uint8Array(
                    atob(encryptedBase64).split('').map(c => c.charCodeAt(0))
                );
                
                const salt = combined.slice(0, 16);
                const iv = combined.slice(16, 28);
                const encryptedData = combined.slice(28);
                
                const key = await deriveKey(password, salt);
                
                const decryptedBuffer = await crypto.subtle.decrypt(
                    { name: 'AES-GCM', iv: iv },
                    key,
                    encryptedData
                );
                
                const decoder = new TextDecoder();
                const decryptedString = decoder.decode(decryptedBuffer);
                return JSON.parse(decryptedString);
            } catch (error) {
                console.error('Decryption failed:', error);
                throw new Error('Wrong password or corrupted data');
            }
        }

        // UI FUNCTIONS
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `<p style="font-weight: 600; font-size: 14px;">${message}</p>`;
            container.appendChild(notification);
            setTimeout(() => {
                notification.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        function updateStatus(text, details, dotClass = 'status-offline', badgeText = 'Offline', badgeClass = 'badge-default') {
            document.getElementById('statusText').textContent = text;
            document.getElementById('statusDetails').textContent = details;
            document.getElementById('statusDot').className = `status-dot ${dotClass}`;
            document.getElementById('statusBadge').textContent = badgeText;
            document.getElementById('statusBadge').className = `badge ${badgeClass}`;
        }

        function switchToCallScreen() {
            document.getElementById('callScreen').classList.add('active');
            positionLocalVideo();
        }

        function switchToSetupScreen() {
            document.getElementById('callScreen').classList.remove('active');
        }

        // DRAGGABLE LOCAL VIDEO
        function positionLocalVideo() {
            const video = document.getElementById('localVideo');
            video.style.right = '20px';
            video.style.top = '80px';
        }

        function initDraggableVideo() {
            const video = document.getElementById('localVideo');
            const wrapper = document.getElementById('videoWrapper');

            function startDrag(e) {
                if (state.isLocalVideoHidden) return;
                state.dragData.isDragging = true;
                const touch = e.type === 'touchstart' ? e.touches[0] : e;
                state.dragData.startX = touch.clientX;
                state.dragData.startY = touch.clientY;
                const rect = video.getBoundingClientRect();
                state.dragData.initialX = rect.left;
                state.dragData.initialY = rect.top;
                video.style.transition = 'none';
                e.preventDefault();
            }

            function doDrag(e) {
                if (!state.dragData.isDragging) return;
                const touch = e.type === 'touchmove' ? e.touches[0] : e;
                const dx = touch.clientX - state.dragData.startX;
                const dy = touch.clientY - state.dragData.startY;
                const newX = state.dragData.initialX + dx;
                const newY = state.dragData.initialY + dy;
                const wrapperRect = wrapper.getBoundingClientRect();
                const videoRect = video.getBoundingClientRect();
                const maxX = wrapperRect.width - videoRect.width;
                const maxY = wrapperRect.height - videoRect.height;
                const clampedX = Math.max(0, Math.min(newX, maxX));
                const clampedY = Math.max(0, Math.min(newY, maxY));
                video.style.left = clampedX + 'px';
                video.style.top = clampedY + 'px';
                video.style.right = 'auto';
                video.style.bottom = 'auto';
                e.preventDefault();
            }

            function endDrag() {
                if (state.dragData.isDragging) {
                    state.dragData.isDragging = false;
                    video.style.transition = 'opacity 0.3s';
                }
            }

            video.addEventListener('mousedown', startDrag);
            video.addEventListener('touchstart', startDrag, { passive: false });
            document.addEventListener('mousemove', doDrag);
            document.addEventListener('touchmove', doDrag, { passive: false });
            document.addEventListener('mouseup', endDrag);
            document.addEventListener('touchend', endDrag);
        }

        // MEDIA FUNCTIONS
        async function initializeMedia() {
            try {
                const quality = document.getElementById('videoQuality')?.value || 'hd';
                const constraints = {
                    audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
                    video: {
                        width: quality === 'fullhd' ? { ideal: 1920 } : quality === 'hd' ? { ideal: 1280 } : { ideal: 640 },
                        height: quality === 'fullhd' ? { ideal: 1080 } : quality === 'hd' ? { ideal: 720 } : { ideal: 480 },
                        frameRate: { ideal: 30 }
                    }
                };

                state.localStream = await navigator.mediaDevices.getUserMedia(constraints);
                document.getElementById('localVideo').srcObject = state.localStream;
                showNotification('Camera and microphone ready', 'success');
                return true;
            } catch (error) {
                console.error('Media error:', error);
                showNotification('Failed to access media devices', 'error');
                return false;
            }
        }

        // WEBRTC FUNCTIONS
        async function createPeerConnection() {
            state.peerConnection = new RTCPeerConnection(rtcConfig);

            if (state.localStream) {
                state.localStream.getTracks().forEach(track => {
                    state.peerConnection.addTrack(track, state.localStream);
                });
            }

            state.peerConnection.ontrack = (event) => {
                console.log('Remote track received:', event.track.kind);
                const remoteVideo = document.getElementById('remoteVideo');
                if (remoteVideo.srcObject !== event.streams[0]) {
                    remoteVideo.srcObject = event.streams[0];
                    showNotification('Remote video connected!', 'success');
                    switchToCallScreen();
                }
            };

            state.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    console.log('ICE candidate:', event.candidate.candidate);
                }
            };

            state.peerConnection.onconnectionstatechange = () => {
                const connectionState = state.peerConnection.connectionState;
                console.log('Connection state:', connectionState);

                if (connectionState === 'connected') {
                    updateStatus('Connected', 'Encrypted call in progress', 'status-online', 'Connected', 'badge-success');
                    document.getElementById('callTitle').textContent = '🔐 Encrypted Call';
                } else if (connectionState === 'connecting') {
                    updateStatus('Connecting', 'Establishing encrypted connection', 'status-connecting', 'Connecting', 'badge-warning');
                } else if (connectionState === 'disconnected' || connectionState === 'failed') {
                    updateStatus('Disconnected', 'Connection lost', 'status-offline', 'Offline', 'badge-default');
                    showNotification('Connection lost', 'error');
                }
            };

            return state.peerConnection;
        }

        // SIGNALING
        async function sendSignal(signal) {
            console.log('Sending signal:', signal.type);
            const encryptedSignal = await encryptData(signal, state.roomPassword);
            
            const response = await fetch(`?action=send_signal&room=${state.roomId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    role: state.role, 
                    signal: encryptedSignal
                })
            });
            const result = await response.json();
            console.log('Signal sent:', result);
            return result;
        }

        async function waitForSignal() {
            console.log('Waiting for signal as:', state.role);
            const response = await fetch(`?action=get_signal&room=${state.roomId}&role=${state.role}`);
            const result = await response.json();
            
            console.log('Received signal result:', result.success);
            
            if (result.success && result.signal) {
                try {
                    const decryptedSignal = await decryptData(result.signal, state.roomPassword);
                    console.log('Signal decrypted:', decryptedSignal.type);
                    return { success: true, signal: decryptedSignal };
                } catch (error) {
                    console.error('Decryption error:', error);
                    showNotification('Wrong password - decryption failed', 'error');
                    return { success: false, error: 'Decryption failed' };
                }
            }
            
            return result;
        }

        async function getRoomInfo(roomId) {
            const response = await fetch(`?action=get_room_info&room=${roomId}`);
            return await response.json();
        }

        // ROOM FUNCTIONS
        async function createRoom() {
            const password = document.getElementById('roomPasswordInput').value;

            if (!password || password.length < 6) {
                showNotification('Password must be at least 6 characters', 'error');
                return;
            }

            if (!await initializeMedia()) return;

            state.roomPassword = password;
            updateStatus('Creating room', 'Generating encryption keys', 'status-connecting', 'Creating', 'badge-warning');

            const response = await fetch('?action=create_room', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();

            if (!data.success) {
                showNotification('Failed to create room', 'error');
                return;
            }

            state.roomId = data.roomId;
            state.role = 'host';

            const roomUrl = `${window.location.href.split('?')[0]}?room=${state.roomId}`;
            document.getElementById('roomUrl').textContent = roomUrl;
            document.getElementById('startSection').classList.add('hidden');
            document.getElementById('roomSection').classList.remove('hidden');

            updateStatus('Waiting', 'Waiting for guest', 'status-connecting', 'Hosting', 'badge-warning');
            showNotification('Encrypted room created!', 'success');

            startAsHost();
        }

        async function startAsHost() {
            console.log('Starting as host...');
            await createPeerConnection();

            const offer = await state.peerConnection.createOffer();
            await state.peerConnection.setLocalDescription(offer);
            console.log('Offer created');

            await new Promise((resolve) => {
                if (state.peerConnection.iceGatheringState === 'complete') {
                    resolve();
                } else {
                    state.peerConnection.addEventListener('icegatheringstatechange', function check() {
                        if (state.peerConnection.iceGatheringState === 'complete') {
                            state.peerConnection.removeEventListener('icegatheringstatechange', check);
                            resolve();
                        }
                    });
                }
            });
            console.log('ICE gathering complete');

            await sendSignal({
                type: 'offer',
                sdp: state.peerConnection.localDescription.sdp
            });

            console.log('Offer sent, waiting for answer...');
            updateStatus('Waiting', 'Encrypted offer sent', 'status-connecting', 'Hosting', 'badge-warning');

            const result = await waitForSignal();
            if (result.success && result.signal) {
                console.log('Answer received, setting remote description');
                await state.peerConnection.setRemoteDescription(new RTCSessionDescription(result.signal));
                showNotification('Guest joined with correct password!', 'success');
            } else {
                console.error('Failed to receive answer:', result.error);
                showNotification('Failed to connect: ' + (result.error || 'Unknown error'), 'error');
            }
        }

        async function joinRoom() {
            const password = document.getElementById('joinPasswordInput').value;

            if (!password) {
                showNotification('Please enter the room password', 'error');
                return;
            }

            if (!await initializeMedia()) return;

            state.roomPassword = password;
            state.role = 'guest';
            
            console.log('Joining as guest...');
            updateStatus('Joining', 'Fetching encrypted offer', 'status-connecting', 'Joining', 'badge-warning');

            await createPeerConnection();

            console.log('Waiting for host offer...');
            const result = await waitForSignal();
            
            if (!result.success || !result.signal) {
                showNotification('Wrong password or connection failed', 'error');
                updateStatus('Failed', 'Incorrect password', 'status-offline', 'Error', 'badge-default');
                return;
            }

            console.log('Offer received and decrypted successfully');
            
            try {
                await state.peerConnection.setRemoteDescription(new RTCSessionDescription(result.signal));
                console.log('Remote description set');
            } catch (error) {
                console.error('ERROR setting remote description:', error.message);
                showNotification('Failed to process offer', 'error');
                return;
            }

            console.log('Creating answer...');
            const answer = await state.peerConnection.createAnswer();
            await state.peerConnection.setLocalDescription(answer);

            await new Promise((resolve) => {
                if (state.peerConnection.iceGatheringState === 'complete') {
                    resolve();
                } else {
                    state.peerConnection.addEventListener('icegatheringstatechange', function check() {
                        if (state.peerConnection.iceGatheringState === 'complete') {
                            state.peerConnection.removeEventListener('icegatheringstatechange', check);
                            resolve();
                        }
                    });
                }
            });
            console.log('ICE gathering complete');

            await sendSignal({
                type: 'answer',
                sdp: state.peerConnection.localDescription.sdp
            });

            console.log('Answer sent! Waiting for connection...');
            showNotification('Password correct! Connecting...', 'success');
        }

        // CALL CONTROLS
        function toggleAudio() {
            state.isAudioEnabled = !state.isAudioEnabled;
            if (state.localStream) {
                state.localStream.getAudioTracks().forEach(track => track.enabled = state.isAudioEnabled);
            }
            const btn = document.getElementById('toggleAudioBtn');
            btn.classList.toggle('off', !state.isAudioEnabled);
            btn.innerHTML = state.isAudioEnabled ? '🎤' : '🔇';
            showNotification(state.isAudioEnabled ? 'Mic on' : 'Mic off', 'info');
        }

        function toggleVideo() {
            state.isVideoEnabled = !state.isVideoEnabled;
            if (state.localStream) {
                state.localStream.getVideoTracks().forEach(track => track.enabled = state.isVideoEnabled);
            }
            const btn = document.getElementById('toggleVideoBtn');
            btn.classList.toggle('off', !state.isVideoEnabled);
            btn.innerHTML = state.isVideoEnabled ? '📹' : '🚫';
            
            if (!state.isVideoEnabled) {
                state.isAudioOnlyMode = true;
                document.getElementById('audioOnlyOverlay').classList.add('active');
                document.getElementById('audioOnlyBtn').classList.add('active');
            } else {
                state.isAudioOnlyMode = false;
                document.getElementById('audioOnlyOverlay').classList.remove('active');
                document.getElementById('audioOnlyBtn').classList.remove('active');
            }
            
            showNotification(state.isVideoEnabled ? 'Camera on' : 'Camera off', 'info');
        }

        function hideSelfVideo() {
            state.isLocalVideoHidden = !state.isLocalVideoHidden;
            const localVideo = document.getElementById('localVideo');
            const btn = document.getElementById('hideSelfBtn');
            
            if (state.isLocalVideoHidden) {
                localVideo.classList.add('hidden-preview');
                btn.classList.add('active');
            } else {
                localVideo.classList.remove('hidden-preview');
                btn.classList.remove('active');
            }
            
            showNotification(state.isLocalVideoHidden ? 'Self view hidden' : 'Self view visible', 'info');
        }

        function toggleAudioOnly() {
            state.isAudioOnlyMode = !state.isAudioOnlyMode;
            const overlay = document.getElementById('audioOnlyOverlay');
            const btn = document.getElementById('audioOnlyBtn');
            
            if (state.isAudioOnlyMode) {
                overlay.classList.add('active');
                btn.classList.add('active');
                state.isVideoEnabled = false;
                if (state.localStream) {
                    state.localStream.getVideoTracks().forEach(track => track.enabled = false);
                }
                document.getElementById('toggleVideoBtn').classList.add('off');
                document.getElementById('toggleVideoBtn').innerHTML = '🚫';
                showNotification('Audio only mode', 'info');
            } else {
                overlay.classList.remove('active');
                btn.classList.remove('active');
                state.isVideoEnabled = true;
                if (state.localStream) {
                    state.localStream.getVideoTracks().forEach(track => track.enabled = true);
                }
                document.getElementById('toggleVideoBtn').classList.remove('off');
                document.getElementById('toggleVideoBtn').innerHTML = '📹';
                showNotification('Video mode', 'info');
            }
        }

        function toggleFullscreen() {
            const wrapper = document.getElementById('videoWrapper');
            
            if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.mozFullScreenElement) {
                if (wrapper.requestFullscreen) {
                    wrapper.requestFullscreen();
                } else if (wrapper.webkitRequestFullscreen) {
                    wrapper.webkitRequestFullscreen();
                } else if (wrapper.mozRequestFullScreen) {
                    wrapper.mozRequestFullScreen();
                }
                showNotification('Fullscreen mode', 'info');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                }
                showNotification('Exit fullscreen', 'info');
            }
        }

        function hangup() {
            if (state.localStream) {
                state.localStream.getTracks().forEach(track => track.stop());
                state.localStream = null;
            }
            if (state.peerConnection) {
                state.peerConnection.close();
                state.peerConnection = null;
            }
            document.getElementById('remoteVideo').srcObject = null;
            document.getElementById('localVideo').srcObject = null;
            location.reload();
        }

        // EVENT LISTENERS
        document.getElementById('createRoomBtn').addEventListener('click', createRoom);
        document.getElementById('joinRoomBtn').addEventListener('click', joinRoom);
        document.getElementById('toggleAudioBtn').addEventListener('click', toggleAudio);
        document.getElementById('toggleVideoBtn').addEventListener('click', toggleVideo);
        document.getElementById('hideSelfBtn').addEventListener('click', hideSelfVideo);
        document.getElementById('audioOnlyBtn').addEventListener('click', toggleAudioOnly);
        document.getElementById('fullscreenBtn').addEventListener('click', toggleFullscreen);
        document.getElementById('hangupBtn').addEventListener('click', hangup);

        document.getElementById('copyRoomUrlBtn').addEventListener('click', () => {
            const url = document.getElementById('roomUrl').textContent;
            navigator.clipboard.writeText(url);
            showNotification('Room link copied! Send password separately.', 'success');
        });

        document.getElementById('roomUrl').addEventListener('click', () => {
            const url = document.getElementById('roomUrl').textContent;
            navigator.clipboard.writeText(url);
            showNotification('Room link copied!', 'success');
        });

        document.getElementById('joinPasswordInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('joinRoomBtn').click();
            }
        });

        // INITIALIZATION
        (async function init() {
            initDraggableVideo();
            
            const urlParams = new URLSearchParams(window.location.search);
            const roomId = urlParams.get('room');

            if (roomId) {
                state.roomId = roomId;
                const roomInfo = await getRoomInfo(roomId);
                
                if (roomInfo.success) {
                    document.getElementById('startSection').classList.add('hidden');
                    document.getElementById('joinSection').classList.remove('hidden');
                    updateStatus('Ready', 'Enter password to decrypt and join', 'status-connecting', 'Ready', 'badge-warning');
                } else {
                    showNotification('Room not found or expired', 'error');
                }
            } else {
                updateStatus('Ready', 'Create an encrypted room to start', 'status-offline', 'Ready', 'badge-default');
            }
        })();
    </script>
</body>
</html>