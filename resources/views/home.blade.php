<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Recognition Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8 max-w-md">
        <h1 class="text-3xl font-bold text-center text-gray-800 mb-8">Food Recognition Prototype</h1>

        <!-- Camera Section -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <button id="start-camera" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                Open Camera
            </button>
            <video id="camera-preview" class="hidden w-full mt-4 rounded-lg border-2 border-gray-200"></video>
            <button id="capture-btn" class="hidden w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg mt-4 transition">
                Capture
            </button>
        </div>

        <!-- Upload Section -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <form id="upload-form" class="space-y-4">
                <input type="file" id="image-input" accept="image/*" class="hidden">
                <button type="button" id="choose-file-btn" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Choose File
                </button>
                <div id="preview-container" class="mt-4"></div>
                <button type="submit" id="submit-btn" class="hidden w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Analyze
                </button>
                <div id="loader" class="hidden text-center py-4">
                    <div class="loader"></div>
                    <p class="text-gray-600 mt-2">Analyzing...</p>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div id="result" class="hidden bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Results</h2>
            <div id="prediction-text" class="mb-4 p-4 bg-gray-50 rounded"></div>
            <div id="nutrition-text" class="p-4 bg-gray-50 rounded"></div>
            <button id="retry-btn" class="w-full mt-4 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition">
                Try Again
            </button>
        </div>

        <!-- Error Alert -->
        <div id="error-alert" class="hidden fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"></div>
    </div>

    <script>
        // DOM Elements
        const cameraPreview = document.getElementById('camera-preview');
        const startCameraBtn = document.getElementById('start-camera');
        const captureBtn = document.getElementById('capture-btn');
        const imageInput = document.getElementById('image-input');
        const chooseFileBtn = document.getElementById('choose-file-btn');
        const submitBtn = document.getElementById('submit-btn');
        const resultDiv = document.getElementById('result');
        const predictionText = document.getElementById('prediction-text');
        const nutritionText = document.getElementById('nutrition-text');
        const previewContainer = document.getElementById('preview-container');
        const loader = document.getElementById('loader');
        const errorAlert = document.getElementById('error-alert');
        const retryBtn = document.getElementById('retry-btn');

        let cameraStream = null;

        // API Configuration
        const API_URL = 'https://24.144.117.151:5000/predict';
        const CSRF_TOKEN = '{{ csrf_token() }}';

        // Camera Handling
        startCameraBtn.addEventListener('click', async () => {
            try {
                if (cameraStream) {
                    cameraStream.getTracks().forEach(track => track.stop());
                }

                const constraints = {
                    video: { facingMode: 'environment', width: 1280, height: 720 }
                };

                cameraStream = await navigator.mediaDevices.getUserMedia(constraints)
                    .catch(() => navigator.mediaDevices.getUserMedia({ video: true }));

                cameraPreview.srcObject = cameraStream;
                cameraPreview.classList.remove('hidden');
                captureBtn.classList.remove('hidden');
                chooseFileBtn.classList.add('hidden');
                hideError();
            } catch (error) {
                showError(`Camera Error: ${error.message}`);
            }
        });

        // Image Capture
        captureBtn.addEventListener('click', () => {
            const canvas = document.createElement('canvas');
            canvas.width = cameraPreview.videoWidth;
            canvas.height = cameraPreview.videoHeight;
            canvas.getContext('2d').drawImage(cameraPreview, 0, 0);

            canvas.toBlob(blob => {
                imageInput.files = createFileList(blob);
                showPreview(blob);
                cameraStream.getTracks().forEach(track => track.stop());
                cameraPreview.classList.add('hidden');
                captureBtn.classList.add('hidden');
            }, 'image/jpeg', 0.9);
        });

        // File Handling
        chooseFileBtn.addEventListener('click', () => imageInput.click());
        imageInput.addEventListener('change', () => {
            if (imageInput.files.length > 0) {
                showPreview(URL.createObjectURL(imageInput.files[0]));
            }
        });

        // Form Submission
        document.getElementById('upload-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!imageInput.files.length) return;

            showLoading(true);
            hideError();
            resultDiv.classList.add('hidden');

            try {
                const formData = new FormData();
                formData.append('image', imageInput.files[0]);
                formData.append('_token', CSRF_TOKEN);

                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                    credentials: 'omit'
                });

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const data = await response.json();
                showResults(data);
            } catch (error) {
                handleApiError(error);
            } finally {
                showLoading(false);
            }
        });

        // Retry Functionality
        retryBtn.addEventListener('click', () => {
            resultDiv.classList.add('hidden');
            previewContainer.innerHTML = '';
            imageInput.value = '';
            startCameraBtn.classList.remove('hidden');
            chooseFileBtn.classList.remove('hidden');
        });

        // Helper Functions
        function createFileList(blob) {
            const file = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            return dataTransfer.files;
        }

        function showPreview(imageSrc) {
            previewContainer.innerHTML = '';
            const img = document.createElement('img');
            img.src = typeof imageSrc === 'string' ? imageSrc : URL.createObjectURL(imageSrc);
            img.classList.add('w-full', 'h-48', 'object-cover', 'rounded-lg');
            previewContainer.appendChild(img);
            submitBtn.classList.remove('hidden');
        }

        function showLoading(show) {
            loader.classList.toggle('hidden', !show);
            submitBtn.classList.toggle('hidden', show);
        }

        function showResults(data) {
            predictionText.innerHTML = `
                <p class="font-semibold">${data.prediction?.predicted_label || 'Unknown Food'}</p>
                <div class="mt-2 space-y-1">
                    ${(data.prediction?.probabilities || []).map((p, i) => `
                        <div class="flex justify-between">
                            <span>${['Fried Potatoes', 'Fried Rice', 'Pizza', 'Ice Cream'][i]}</span>
                            <span>${(p * 100).toFixed(2)}%</span>
                        </div>
                    `).join('')}
                </div>
            `;

            nutritionText.innerHTML = data.nutrition?.error ? `
                <div class="text-red-500">${data.nutrition.error}</div>
            ` : `
                <div class="space-y-2">
                    <p><span class="font-semibold">Calories:</span> ${data.nutrition?.calories || 'N/A'}</p>
                    <p><span class="font-semibold">Protein:</span> ${data.nutrition?.protein || 'N/A'}</p>
                    <p><span class="font-semibold">Carbs:</span> ${data.nutrition?.total_carbohydrate?.value || 'N/A'}</p>
                    <p><span class="font-semibold">Fat:</span> ${data.nutrition?.total_fat?.value || 'N/A'}</p>
                </div>
            `;

            resultDiv.classList.remove('hidden');
        }

        function handleApiError(error) {
            let message = 'Analysis failed. Please try again.';
            if (error.message.includes('Failed to fetch')) {
                message = `Connection error: Visit <a href="${API_URL}" target="_blank" class="underline">the API endpoint</a> first to accept the certificate`;
            }
            showError(message);
        }

        function showError(message) {
            errorAlert.innerHTML = message;
            errorAlert.classList.remove('hidden');
            setTimeout(() => errorAlert.classList.add('hidden'), 5000);
        }

        function hideError() {
            errorAlert.classList.add('hidden');
        }
    </script>
</body>
</html>
