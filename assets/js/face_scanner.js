(function () {
  const APP_BASE_URL = window.APP_BASE_URL || '';
  const FACE_MODEL_URL = 'https://justadudewhohacks.github.io/face-api.js/models';
  const FACE_API_CDN = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
  const MATCH_THRESHOLD = 0.48;

  const video = document.getElementById('faceScannerVideo');
  const startBtn = document.getElementById('startScannerBtn');
  const scanBtn = document.getElementById('scanOnceBtn');
  const stopBtn = document.getElementById('stopScannerBtn');
  const scannerStatus = document.getElementById('scannerStatus');
  const scannerIcon = document.getElementById('scannerIcon');
  const recognitionResult = document.getElementById('recognitionResult');
  const scannerMessage = document.getElementById('scannerMessage');
  const classSession = document.getElementById('classSession');

  let cameraStream = null;
  let registry = [];
  let faceApiLoadPromise = null;
  let faceModelsLoadPromise = null;

  function apiUrl(path) {
    return (APP_BASE_URL ? APP_BASE_URL : '') + '/' + path.replace(/^\/+/, '');
  }

  function setStatus(text, cls) {
    scannerStatus.className = 'status-pill ' + cls;
    scannerStatus.textContent = text;
  }

  function setResult(text, state, message) {
    scannerIcon.className = 'scan-pad mb-3 ' + (state || '');
    recognitionResult.textContent = text;
    scannerMessage.textContent = message || '';
  }

  async function requestJson(url) {
    const response = await fetch(url);
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || data.ok === false) {
      throw new Error((data && data.message) || 'Request failed.');
    }
    return data;
  }

  async function loadRegistry() {
    const data = await requestJson(apiUrl('api/face_registry.php'));
    registry = (data.students || []).filter(student => Array.isArray(student.descriptor));

    if (registry.length === 0) {
      throw new Error('No enrolled face templates found.');
    }
  }

  async function loadSessions() {
    const data = await requestJson(apiUrl('api/face_scan_attendance.php'));
    if (!classSession) return;

    classSession.innerHTML = '<option value="">Select today\'s class</option>';
    (data.sessions || []).forEach(session => {
      const option = document.createElement('option');
      option.value = session.id;
      option.textContent = `${session.title} — ${session.course} (${session.start_time}–${session.end_time})`;
      classSession.appendChild(option);
    });
    classSession.disabled = false;
    if (!data.sessions || data.sessions.length === 0) {
      throw new Error('No class sessions are scheduled for today.');
    }
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
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user' },
      audio: false
    });
    video.srcObject = cameraStream;
    await video.play();
  }

  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(track => track.stop());
      cameraStream = null;
    }
    video.srcObject = null;
  }

  function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  async function captureFaceDescriptor() {
    const detectorOptions = new window.faceapi.TinyFaceDetectorOptions({
      inputSize: 320,
      scoreThreshold: 0.5
    });

    for (let attempt = 0; attempt < 16; attempt += 1) {
      const result = await window.faceapi
        .detectSingleFace(video, detectorOptions)
        .withFaceLandmarks()
        .withFaceDescriptor();

      if (result) {
        return result.descriptor;
      }

      await sleep(250);
    }

    throw new Error('No face detected. Keep the student face centered and try again.');
  }

  function findBestMatch(liveDescriptor) {
    let bestMatch = null;

    registry.forEach(student => {
      const distance = window.faceapi.euclideanDistance(
        new Float32Array(student.descriptor),
        new Float32Array(liveDescriptor)
      );

      if (!bestMatch || distance < bestMatch.distance) {
        bestMatch = { regNo: student.reg_no, distance };
      }
    });

    return bestMatch;
  }

  async function recordAttendance(regNo) {
    if (!classSession || !classSession.value) {
      throw new Error('Select the class being scanned before scanning a face.');
    }
    const response = await fetch(apiUrl('api/face_scan_attendance.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: Number(classSession.value), reg_no: regNo })
    });
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || data.ok === false) {
      throw new Error((data && data.message) || 'Could not record attendance.');
    }
    return data;
  }

  async function scanFace() {
    scanBtn.disabled = true;
    setStatus('Scanning', 'status-pending');
    setResult('Scanning...', 'scanning', 'Hold still while the scanner compares this face.');

    try {
      const liveDescriptor = await captureFaceDescriptor();
      const match = findBestMatch(liveDescriptor);

      if (match && match.distance <= MATCH_THRESHOLD) {
        const attendance = await recordAttendance(match.regNo);
        if (attendance.outcome === 'attended') {
          setStatus('Attended', 'status-verified');
          setResult(`${attendance.reg_no} attended`, 'success', attendance.message);
        } else {
          setStatus('Wrong class', 'status-unverified');
          setResult(`${attendance.reg_no} wrong Class`, 'fail', attendance.message);
        }
        return;
      }

      setStatus('Not recognised', 'status-unverified');
      setResult('Student not recognised', 'fail', 'No registered student face matched this scan.');
    } catch (error) {
      setStatus('Scan failed', 'status-unverified');
      setResult('Scan failed', 'fail', error.message || 'Face scan failed.');
    } finally {
      scanBtn.disabled = !cameraStream;
    }
  }

  startBtn.addEventListener('click', async () => {
    startBtn.disabled = true;
    setStatus('Starting', 'status-pending');
    setResult('Preparing scanner', 'scanning', 'Loading camera and recognition models...');

    try {
      if (!window.isSecureContext) {
        throw new Error('Camera face recognition requires HTTPS or localhost.');
      }
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Camera access is not supported in this browser.');
      }

      await Promise.all([loadFaceApi(), loadRegistry(), loadSessions()]);
      await startCamera();

      scanBtn.disabled = false;
      stopBtn.disabled = false;
      setStatus('Scanner ready', 'status-pending');
      setResult('Ready to scan', '', `Loaded ${registry.length} enrolled face template(s).`);
    } catch (error) {
      stopCamera();
      startBtn.disabled = false;
      scanBtn.disabled = true;
      stopBtn.disabled = true;
      setStatus('Scanner unavailable', 'status-unverified');
      setResult('Scanner unavailable', 'fail', error.message || 'Unable to start scanner.');
    }
  });

  scanBtn.addEventListener('click', () => {
    void scanFace();
  });

  stopBtn.addEventListener('click', () => {
    stopCamera();
    startBtn.disabled = false;
    scanBtn.disabled = true;
    stopBtn.disabled = true;
    setStatus('Scanner stopped', 'status-pending');
    setResult('Waiting for scan', '', 'Start the scanner, face the camera, then scan.');
  });

  if (new URLSearchParams(window.location.search).get('autostart') === '1') {
    window.setTimeout(() => startBtn.click(), 0);
  }
})();
