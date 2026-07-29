(function () {
  const stepMethod = document.getElementById('step-method');
  const stepScan = document.getElementById('step-scan');
  const stepDoor = document.getElementById('step-door');
  const stepResult = document.getElementById('step-result');
  const stepBadge = document.getElementById('stepBadge');

  const scanPad = document.getElementById('scanPad');
  const scanIcon = document.getElementById('scanIcon');
  const scanResultMsg = document.getElementById('scanResultMsg');
  const biometricPanel = document.getElementById('biometricPanel');
  const retryArea = document.getElementById('retryArea');
  const retryBtn = document.getElementById('retryBtn');

  const doorEl = document.getElementById('doorEl');
  const doorMsg = document.getElementById('doorMsg');
  const enteredBtn = document.getElementById('enteredBtn');
  const notEnteredBtn = document.getElementById('notEnteredBtn');

  const resultIcon = document.getElementById('resultIcon');
  const resultMsg = document.getElementById('resultMsg');
  const scanAgainBtn = document.getElementById('scanAgainBtn');

  const APP_BASE_URL = window.APP_BASE_URL || '';
  const FACE_MODEL_URL = 'https://justadudewhohacks.github.io/face-api.js/models';
  const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';

  let currentMethod = 'fingerprint';
  let retryCount = 0;
  let currentLogId = null;
  let cameraStream = null;
  let faceApiLoadPromise = null;
  let faceModelsLoadPromise = null;

  function apiUrl(path) {
    const cleanPath = path.replace(/^\/+/, '');
    return (APP_BASE_URL ? APP_BASE_URL : '') + '/' + cleanPath;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showOnly(el) {
    [stepMethod, stepScan, stepDoor, stepResult].forEach(section => section.classList.add('d-none'));
    el.classList.remove('d-none');
  }

  function setBadge(text, cls) {
    stepBadge.className = 'status-pill ' + cls;
    stepBadge.textContent = text;
  }

  function setPanel(html) {
    biometricPanel.innerHTML = html;
  }

  function clearPanel() {
    biometricPanel.innerHTML = '';
  }

  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(track => track.stop());
      cameraStream = null;
    }
  }

  function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  function bufferToBase64Url(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    let binary = '';
    bytes.forEach(byte => {
      binary += String.fromCharCode(byte);
    });
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function base64UrlToBuffer(value) {
    const padded = value.replace(/-/g, '+').replace(/_/g, '/');
    const base64 = padded + '='.repeat((4 - padded.length % 4) % 4);
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1) {
      bytes[index] = binary.charCodeAt(index);
    }
    return bytes.buffer;
  }

  function serializeWebAuthnCredential(credential) {
    const response = {
      clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON)
    };

    if (credential.response.attestationObject) {
      response.attestationObject = bufferToBase64Url(credential.response.attestationObject);
    }
    if (credential.response.authenticatorData) {
      response.authenticatorData = bufferToBase64Url(credential.response.authenticatorData);
    }
    if (credential.response.signature) {
      response.signature = bufferToBase64Url(credential.response.signature);
    }
    if (credential.response.userHandle) {
      response.userHandle = bufferToBase64Url(credential.response.userHandle);
    }

    return {
      id: credential.id,
      rawId: bufferToBase64Url(credential.rawId),
      type: credential.type,
      response
    };
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json().catch(() => null);
    if (!response.ok) {
      throw new Error((data && data.message) || 'Request failed.');
    }
    return data;
  }

  async function getBiometricStatus() {
    return requestJson(apiUrl('api/biometric_status.php'));
  }

  async function loadFaceApi() {
    if (window.faceapi) {
      if (!faceModelsLoadPromise) {
        faceModelsLoadPromise = Promise.all([
          window.faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODEL_URL),
          window.faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODEL_URL),
          window.faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODEL_URL)
        ]);
      }
      await faceModelsLoadPromise;
      return;
    }

    if (!faceApiLoadPromise) {
      faceApiLoadPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = FACE_API_CDN;
        script.onload = resolve;
        script.onerror = () => reject(new Error('Unable to load face recognition library.'));
        document.head.appendChild(script);
      });
    }

    await faceApiLoadPromise;
    faceModelsLoadPromise = Promise.all([
      window.faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODEL_URL),
      window.faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODEL_URL),
      window.faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODEL_URL)
    ]);
    await faceModelsLoadPromise;
  }

  async function startCamera() {
    if (cameraStream) {
      return;
    }

    const video = document.getElementById('faceVideo');
    if (!video) {
      throw new Error('Camera panel is not ready.');
    }

    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user' },
      audio: false
    });

    video.srcObject = cameraStream;
    await video.play();
  }

  function faceVideo() {
    return document.getElementById('faceVideo');
  }

  async function captureFaceDescriptor() {
    const video = faceVideo();
    if (!video || !window.faceapi) {
      throw new Error('Face recognition is not ready yet.');
    }

    const detectorOptions = new window.faceapi.TinyFaceDetectorOptions({
      inputSize: 320,
      scoreThreshold: 0.5
    });

    for (let attempt = 0; attempt < 20; attempt += 1) {
      const result = await window.faceapi
        .detectSingleFace(video, detectorOptions)
        .withFaceLandmarks()
        .withFaceDescriptor();

      if (result) {
        return Array.from(result.descriptor);
      }

      await sleep(250);
    }

    throw new Error('No face was detected. Keep your face in view and try again.');
  }

  async function getFaceTemplate() {
    return requestJson(apiUrl('api/face_template.php'));
  }

  async function saveFaceTemplate(descriptor) {
    return requestJson(apiUrl('api/face_template.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ descriptor, sample_count: 1 })
    });
  }

  function setCameraPanel(message) {
    setPanel(`
      <div class="small text-muted mb-2">${escapeHtml(message)}</div>
      <div class="ratio ratio-4x3 bg-dark rounded overflow-hidden border border-secondary-subtle">
        <video id="faceVideo" autoplay muted playsinline style="width:100%;height:100%;object-fit:cover;"></video>
      </div>
      <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
        <button class="btn btn-primary" id="faceScanBtn">Scan Face</button>
        <button class="btn btn-outline-secondary" id="faceResetBtn">Reset Camera</button>
      </div>
    `);

    document.getElementById('faceScanBtn').addEventListener('click', () => {
      void captureAndVerifyFace();
    });

    document.getElementById('faceResetBtn').addEventListener('click', () => {
      stopCamera();
      void startCamera().catch(error => {
        scanResultMsg.textContent = error.message || 'Unable to restart camera.';
      });
    });
  }

  async function captureAndVerifyFace() {
    try {
      scanResultMsg.textContent = 'Capturing your face...';
      const templateResponse = await getFaceTemplate();
      const enrolledDescriptor = templateResponse.enrolled ? JSON.parse(templateResponse.descriptor) : null;

      if (!enrolledDescriptor) {
        throw new Error('No reference face found. Contact your administrator to re-enroll.');
      }

      const liveDescriptor = await captureFaceDescriptor();
      const distance = window.faceapi.euclideanDistance(new Float32Array(enrolledDescriptor), new Float32Array(liveDescriptor));
      const verified = distance <= 0.48;

      if (!verified) {
        throw new Error('Face mismatch. Try again with better lighting and keep your face centered.');
      }

      await completeAttendanceVerification('face', 'camera_face', true, { face_distance: distance });
      stopCamera();
    } catch (error) {
      scanResultMsg.textContent = error.message || 'Face capture failed.';
      retryArea.classList.remove('d-none');
    }
  }

  function setWebAuthnPanel(message) {
    setPanel(`
      <div class="small text-muted">${escapeHtml(message)}</div>
    `);
  }

  async function completeAttendanceVerification(method, biometricMode, biometricVerified, biometricPayload) {
    const data = await requestJson(apiUrl('api/verify.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        method,
        retry_count: retryCount,
        session_id: SESSION_ID,
        biometric_mode: biometricMode,
        biometric_verified: biometricVerified,
        biometric_payload: biometricPayload
      })
    });

    if (!data.verified) {
      throw new Error(data.message || 'Biometric verification failed.');
    }

    currentLogId = data.log_id;
    scanPad.className = 'scan-pad mb-3 success';
    scanIcon.className = 'bi bi-check-lg';
    scanResultMsg.textContent = data.message || 'Biometric verified.';
    showDoor(data.message || 'Verified - the classroom door has opened!');
  }

  async function beginWebAuthnOptions(mode) {
    const data = await requestJson(apiUrl(`api/webauthn_challenge.php?mode=${encodeURIComponent(mode)}`));
    const options = data.options;
    options.challenge = base64UrlToBuffer(options.challenge);
    options.user.id = base64UrlToBuffer(options.user.id);
    if (Array.isArray(options.allowCredentials)) {
      options.allowCredentials = options.allowCredentials.map(credential => ({
        ...credential,
        id: base64UrlToBuffer(credential.id)
      }));
    }
    return options;
  }

  async function enrollWebAuthnDevice() {
    const options = await beginWebAuthnOptions('register');
    const credential = await navigator.credentials.create({ publicKey: options });
    if (!credential) {
      throw new Error('Biometric enrollment was cancelled.');
    }

    const payload = serializeWebAuthnCredential(credential);
    const data = await requestJson(apiUrl('api/webauthn_enroll.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!data.ok) {
      throw new Error(data.message || 'Device enrollment failed.');
    }
  }

  async function authenticateWebAuthnDevice() {
    const options = await beginWebAuthnOptions('auth');
    if (!options.allowCredentials || options.allowCredentials.length === 0) {
      throw new Error('No enrolled biometric credential was found for this account.');
    }

    const credential = await navigator.credentials.get({ publicKey: options });
    if (!credential) {
      throw new Error('Biometric verification was cancelled.');
    }

    const payload = serializeWebAuthnCredential(credential);
    await completeAttendanceVerification('fingerprint', 'webauthn', true, payload);
  }

  async function runWebAuthnFlow() {
    showOnly(stepScan);
    setBadge('Step 2 - Platform biometrics', 'status-pending');
    scanIcon.className = 'bi bi-fingerprint';
    scanPad.className = 'scan-pad mb-3 scanning';
    scanResultMsg.textContent = 'Preparing your computer biometrics...';
    retryArea.classList.add('d-none');
    setWebAuthnPanel('Approve the browser prompt using Windows Hello, Touch ID, Face ID, or another platform authenticator.');

    try {
      if (!window.isSecureContext) {
        throw new Error('Fingerprint / Face ID requires HTTPS or localhost. Open the app from http://localhost/... or configure HTTPS.');
      }

      if (!window.PublicKeyCredential || !navigator.credentials) {
        throw new Error('WebAuthn is not supported in this browser.');
      }

      const status = await getBiometricStatus();
      if (!status.webauthn_enrolled) {
        scanResultMsg.textContent = 'Registering this device for biometric access...';
        setWebAuthnPanel('First-time setup: approve the prompt to save this device as a biometric authenticator.');
        await enrollWebAuthnDevice();
      }

      scanResultMsg.textContent = 'Waiting for your biometric confirmation...';
      setWebAuthnPanel('Use your configured biometric method now.');
      await authenticateWebAuthnDevice();
    } catch (error) {
      scanPad.className = 'scan-pad mb-3 fail';
      scanResultMsg.textContent = error.message || 'Biometric verification failed.';
      setWebAuthnPanel(error.message || 'Biometric verification failed.');
      retryArea.classList.remove('d-none');
    }
  }

  async function runFaceRecognition() {
    showOnly(stepScan);
    setBadge('Step 2 - Camera face recognition', 'status-pending');
    scanIcon.className = 'bi bi-person-bounding-box';
    scanPad.className = 'scan-pad mb-3 scanning';
    scanResultMsg.textContent = 'Loading camera and face recognition models...';
    retryArea.classList.add('d-none');

    try {
      if (!window.isSecureContext) {
        throw new Error('Camera face recognition requires HTTPS or localhost. Open the app from http://localhost/... or configure HTTPS.');
      }

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Camera access is not supported in this browser.');
      }

      await loadFaceApi();
      scanResultMsg.textContent = 'Camera ready. Click Scan Face to capture.';
      setCameraPanel('Allow camera access, then click Scan Face.');
      await startCamera();
    } catch (error) {
      scanPad.className = 'scan-pad mb-3 fail';
      scanResultMsg.textContent = error.message || 'Camera face recognition failed.';
      setCameraPanel(error.message || 'Camera face recognition failed.');
      retryArea.classList.remove('d-none');
    }
  }

  async function startMethodFlow(method) {
    currentMethod = method;
    clearPanel();
    stopCamera();

    if (method === 'fingerprint') {
      await runWebAuthnFlow();
      return;
    }

    if (method === 'face') {
      await runFaceRecognition();
      return;
    }

    showOnly(stepScan);
    setBadge('Iris scan unavailable', 'status-unverified');
    scanPad.className = 'scan-pad mb-3 fail';
    scanIcon.className = 'bi bi-eye-slash';
    scanResultMsg.textContent = 'Iris scanning is not available in a standard browser. It needs a native device SDK or desktop app.';
    setPanel('<div class="text-muted small">Use fingerprint/Face ID or camera face recognition instead.</div>');
    retryArea.classList.remove('d-none');
  }

  document.querySelectorAll('.method-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      retryCount = 0;
      void startMethodFlow(btn.dataset.method);
    });
  });

  retryBtn.addEventListener('click', () => {
    retryCount += 1;
    if (currentMethod === 'face') {
      void runFaceRecognition();
    } else if (currentMethod === 'fingerprint') {
      void runWebAuthnFlow();
    } else {
      void startMethodFlow(currentMethod);
    }
  });

  function showDoor(message) {
    showOnly(stepDoor);
    setBadge('Step 3 - Door opening', 'status-verified');
    doorEl.classList.remove('open');
    setTimeout(() => doorEl.classList.add('open'), 150);
    doorMsg.textContent = message || 'Verified - the classroom door has opened!';
  }

  enteredBtn.addEventListener('click', () => finalizeAttendance(true));
  notEnteredBtn.addEventListener('click', () => finalizeAttendance(false));

  async function finalizeAttendance(entered) {
    setBadge('Step 4 - Saving', 'status-pending');
    try {
      const data = await requestJson(apiUrl('api/mark_attendance.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ log_id: currentLogId, entered })
      });

      showOnly(stepResult);
      if (data.ok && data.attendance_status === 'verified') {
        resultIcon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
        setBadge('Attendance Verified', 'status-verified');
      } else {
        resultIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
        setBadge('Attendance Unverified', 'status-unverified');
      }
      resultMsg.textContent = data.message || 'Attendance processed.';
      stopCamera();
    } catch (error) {
      showOnly(stepResult);
      resultIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
      resultMsg.textContent = error.message || 'Network error while saving attendance.';
    }
  }

  scanAgainBtn.addEventListener('click', () => {
    retryCount = 0;
    currentLogId = null;
    stopCamera();
    clearPanel();
    showOnly(stepMethod);
    setBadge('Step 1 - Choose method', 'status-pending');
    scanPad.className = 'scan-pad mb-3';
    scanIcon.className = 'bi bi-fingerprint';
    scanResultMsg.textContent = '';
  });
})();
