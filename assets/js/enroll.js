(function () {
  const enrollmentStatus = document.getElementById('enrollmentStatus');
  const enrollmentPanel = document.getElementById('enrollmentPanel');
  const enrollWebauthnBtn = document.getElementById('enrollWebauthnBtn');
  const enrollFaceBtn = document.getElementById('enrollFaceBtn');

  const APP_BASE_URL = window.APP_BASE_URL || '';
  const FACE_MODEL_URL = 'https://justadudewhohacks.github.io/face-api.js/models';
  const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';

  let cameraStream = null;
  let faceApiLoadPromise = null;
  let faceModelsLoadPromise = null;

  function apiUrl(path) {
    const cleanPath = path.replace(/^\/+/, '');
    return (APP_BASE_URL ? APP_BASE_URL : '') + '/' + cleanPath;
  }

  function setStatus(message, cls = 'light') {
    enrollmentStatus.className = `alert alert-${cls} border small mb-3`;
    enrollmentStatus.textContent = message;
  }

  function setPanel(html) {
    enrollmentPanel.innerHTML = html;
  }

  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(track => track.stop());
      cameraStream = null;
    }
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

  function bufferToBase64Url(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    let binary = '';
    bytes.forEach(byte => {
      binary += String.fromCharCode(byte);
    });
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function serializeWebAuthnCredential(credential) {
    const response = {
      clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON)
    };

    if (credential.response.attestationObject) {
      response.attestationObject = bufferToBase64Url(credential.response.attestationObject);
    } else {
      throw new Error('Missing attestationObject - WebAuthn response is invalid.');
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

  async function beginWebAuthnOptions() {
    const params = new URLSearchParams({ mode: 'register' });
    if (window.ADMIN_STUDENT_ID) {
      params.set('student_id', window.ADMIN_STUDENT_ID);
    } else if (window.STUDENT_BIOMETRIC_ID) {
      params.set('student_id', window.STUDENT_BIOMETRIC_ID);
    }

    const data = await requestJson(apiUrl(`api/webauthn_challenge.php?${params.toString()}`));
    const options = data.options;
    options.challenge = base64UrlToBuffer(options.challenge);
    options.user.id = base64UrlToBuffer(options.user.id);
    return options;
  }

  async function enrollWebAuthn() {
    try {
      setStatus('Connecting to your device biometric scanner...', 'info');
      setPanel(`
        <div class="alert alert-info small mb-3">
          <strong>🔒 Secure Connection Initiated</strong>
          <p class="mb-2">Your browser is connecting to your device's biometric authenticator:</p>
          <ul class="small mb-0">
            <li><strong>Windows:</strong> Windows Hello (fingerprint, facial recognition, or PIN)</li>
            <li><strong>Mac:</strong> Touch ID (fingerprint)</li>
            <li><strong>iPhone/iPad:</strong> Face ID or Touch ID</li>
            <li><strong>Android:</strong> Fingerprint or facial recognition</li>
          </ul>
        </div>
        <div class="text-muted small">A browser prompt will appear. Use your device's biometric method to verify your identity.</div>
      `);

      if (!window.isSecureContext) {
        throw new Error('Fingerprint / Face ID requires HTTPS or localhost. Open the app from http://localhost/... or configure HTTPS.');
      }

      if (!window.PublicKeyCredential || !navigator.credentials) {
        throw new Error('WebAuthn is not supported in this browser.');
      }

      const options = await beginWebAuthnOptions();
      const credential = await navigator.credentials.create({ publicKey: options });
      if (!credential) {
        throw new Error('Enrollment was cancelled.');
      }

      const payload = serializeWebAuthnCredential(credential);
      if (window.ADMIN_STUDENT_ID) {
        payload.student_id = window.ADMIN_STUDENT_ID;
      } else if (window.STUDENT_BIOMETRIC_ID) {
        payload.student_id = window.STUDENT_BIOMETRIC_ID;
      }
      const data = await requestJson(apiUrl('api/webauthn_enroll.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      setStatus('✅ Your device biometric has been securely registered!', 'success');
      setPanel(`
        <div class="alert alert-success small">
          <i class="bi bi-check-circle me-2"></i>
          <strong>Device Biometric Enrolled</strong>
          <p class="small mb-0 mt-2">Your fingerprint or Face ID credential is now stored on your device and linked to your student account. You can use your device's biometric scanner to verify attendance.</p>
        </div>
      `);
    } catch (error) {
      setStatus(error.message || 'Device biometric enrollment failed.', 'danger');
      setPanel('<div class="small text-danger">Device biometric enrollment did not complete. Please try again.</div>');
    }
  }

  async function startCamera() {
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

  async function captureFaceDescriptor() {
    const video = document.getElementById('faceVideo');
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
    }

    throw new Error('No face detected. Keep your face in view and try again.');
  }

  async function enrollFace() {
    try {
      setStatus('Loading camera face recognition...', 'info');
      if (!window.isSecureContext) {
        throw new Error('Camera face recognition requires HTTPS or localhost. Open the app from http://localhost/... or configure HTTPS.');
      }

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Camera access is not supported in this browser.');
      }

      setPanel(`
        <div class="small text-muted mb-2">Allow camera access, then click Capture Face.</div>
        <div class="ratio ratio-4x3 bg-dark rounded overflow-hidden border border-secondary-subtle">
          <video id="faceVideo" autoplay muted playsinline style="width:100%;height:100%;object-fit:cover;"></video>
        </div>
        <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
          <button class="btn btn-primary" id="captureFaceBtn">Capture Face</button>
          <button class="btn btn-outline-secondary" id="resetFaceBtn">Reset Camera</button>
        </div>
      `);

      await loadFaceApi();
      await startCamera();

      document.getElementById('captureFaceBtn').addEventListener('click', async () => {
        try {
          setStatus('Capturing your face template...', 'info');
          const descriptor = await captureFaceDescriptor();
          const payload = { descriptor, sample_count: 1 };
          if (window.ADMIN_STUDENT_ID) {
            payload.student_id = window.ADMIN_STUDENT_ID;
          } else if (window.STUDENT_BIOMETRIC_ID) {
            payload.student_id = window.STUDENT_BIOMETRIC_ID;
          }
          const data = await requestJson(apiUrl('api/face_template.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });

          setStatus(data.message || 'Face enrollment completed.', 'success');
          setPanel('<div class="small text-success">Face recognition enrollment completed.</div>');
          stopCamera();
        } catch (error) {
          setStatus(error.message || 'Face enrollment failed.', 'danger');
        }
      });

      document.getElementById('resetFaceBtn').addEventListener('click', () => {
        stopCamera();
        void enrollFace();
      });
    } catch (error) {
      setStatus(error.message || 'Face enrollment failed.', 'danger');
      setPanel('<div class="small text-danger">Camera face enrollment did not complete.</div>');
    }
  }

  enrollWebauthnBtn.addEventListener('click', () => {
    void enrollWebAuthn();
  });

  enrollFaceBtn.addEventListener('click', () => {
    void enrollFace();
  });
})();
