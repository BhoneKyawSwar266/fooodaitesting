<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Recognition AI</title>
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
<body class="bg-gradient-to-br from-blue-100 via-purple-100 to-pink-100 font-sans min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8 max-w-lg">
        <h1 class="text-4xl font-extrabold text-center text-gray-800 mb-8 bg-gradient-to-r from-blue-500 to-purple-500 text-transparent bg-clip-text">
            AI Food Analyzer
        </h1>

        <!-- Camera Section -->
        <div class="camera-section mb-8 bg-white p-6 rounded-xl shadow-lg">
            <button id="start-camera" class="w-full bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105">
                Open Camera
            </button>
            <video id="camera-preview" class="hidden w-full max-w-md mx-auto border-2 border-gray-200 rounded-lg mt-4" autoplay playsinline></video>
            <button id="capture-btn" class="hidden w-full bg-gradient-to-r from-green-500 to-teal-600 hover:from-green-600 hover:to-teal-700 text-white font-semibold py-3 px-6 rounded-lg mt-4 transition duration-300 transform hover:scale-105">
                Capture Image
            </button>
        </div>

        <!-- Upload Section -->
        <div class="upload-section mb-8 bg-white p-6 rounded-xl shadow-lg">
            <form id="upload-form" class="flex flex-col">
                <input type="file" id="image-input" name="image" accept="image/*" required class="hidden">
                <button type="button" id="choose-file-btn" class="w-full bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105">
                    Choose File
                </button>
                <div id="preview-container" class="mt-4 flex flex-col items-center"></div>
                <button type="submit" id="submit-btn" class="hidden w-full bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105 mt-4">
                    Analyze Food
                </button>
                <div id="loader" class="hidden mt-4">
                    <div class="loader"></div>
                    <p class="text-center mt-2 text-gray-600">Analyzing your food...</p>
                </div>
                <div id="error-alert" class="hidden mt-4 p-4 bg-red-100 rounded-lg text-red-700"></div>
            </form>
        </div>

        <!-- Result Section -->
        <div id="result" class="hidden bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Analysis Result</h2>
            <div id="prediction-text" class="text-gray-600 bg-gray-50 p-4 rounded-lg mb-4"></div>
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Nutrition Details</h2>
            <div id="nutrition-text" class="text-gray-600 bg-gray-50 p-4 rounded-lg"></div>
            <button id="reset-btn" class="w-full mt-6 bg-gradient-to-r from-gray-500 to-gray-700 hover:from-gray-600 hover:to-gray-800 text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
                Start Over
            </button>
        </div>
    </div>

    <script>
        // DOM Elements
        const elements = {
            cameraPreview: document.getElementById('camera-preview'),
            startCameraBtn: document.getElementById('start-camera'),
            captureBtn: document.getElementById('capture-btn'),
            imageInput: document.getElementById('image-input'),
            chooseFileBtn: document.getElementById('choose-file-btn'),
            submitBtn: document.getElementById('submit-btn'),
            resultDiv: document.getElementById('result'),
            predictionText: document.getElementById('prediction-text'),
            nutritionText: document.getElementById('nutrition-text'),
            previewContainer: document.getElementById('preview-container'),
            loader: document.getElementById('loader'),
            errorAlert: document.getElementById('error-alert'),
            resetBtn: document.getElementById('reset-btn'),
            uploadForm: document.getElementById('upload-form')
        };

        // State Management
        let cameraStream = null;
        let currentFile = null;

        // API Configuration
        const API_ENDPOINT = window.location.protocol === 'https:'
            ? 'https://your-production-domain.com/predict'
            : 'http://24.144.117.151:5000/predict';

        // Camera Handling
        elements.startCameraBtn.addEventListener('click', async () => {
            try {
                if (cameraStream) {
                    cameraStream.getTracks().forEach(track => track.stop());
                }

                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                cameraStream = await navigator.mediaDevices.getUserMedia(constraints)
                    .catch(() => navigator.mediaDevices.getUserMedia({ video: true }));

                elements.cameraPreview.srcObject = cameraStream;
                elements.cameraPreview.classList.remove('hidden');
                elements.captureBtn.classList.remove('hidden');
                elements.chooseFileBtn.classList.add('hidden');
                hideError();
            } catch (error) {
                showError(`Camera Error: ${error.message}`);
            }
        });

        // Capture Image
        elements.captureBtn.addEventListener('click', () => {
            const canvas = document.createElement('canvas');
            canvas.width = elements.cameraPreview.videoWidth;
            canvas.height = elements.cameraPreview.videoHeight;
            canvas.getContext('2d').drawImage(elements.cameraPreview, 0, 0);

            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                elements.cameraPreview.srcObject = null;
                elements.cameraPreview.classList.add('hidden');
                elements.captureBtn.classList.add('hidden');
            }

            canvas.toBlob(blob => {
                currentFile = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
                showPreview(blob);
            }, 'image/jpeg', 0.9);
        });

        // File Handling
        elements.chooseFileBtn.addEventListener('click', () => elements.imageInput.click());
        elements.imageInput.addEventListener('change', () => {
            if (elements.imageInput.files.length > 0) {
                currentFile = elements.imageInput.files[0];
                showPreview(URL.createObjectURL(currentFile));
            }
        });

        // Form Submission
        elements.uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!currentFile) return;

            showLoading(true);
            hideError();
            elements.resultDiv.classList.add('hidden');

            try {
                const formData = new FormData();
                formData.append('image', currentFile);
                formData.append('_token', '{{ csrf_token() }}');

                const response = await fetchWithTimeout(API_ENDPOINT, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                    credentials: 'omit'
                }, 15000); // 15 second timeout

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || `Server error: ${response.status}`);
                }

                const data = await response.json();
                displayResults(data);
            } catch (error) {
                handleApiError(error);
            } finally {
                showLoading(false);
            }
        });

        // Reset Functionality
        elements.resetBtn.addEventListener('click', () => {
            elements.uploadForm.reset();
            elements.previewContainer.innerHTML = '';
            elements.resultDiv.classList.add('hidden');
            elements.startCameraBtn.classList.remove('hidden');
            elements.chooseFileBtn.classList.remove('hidden');
            currentFile = null;
        });

        // Helper Functions
        function showPreview(imageSrc) {
            elements.previewContainer.innerHTML = '';
            const img = document.createElement('img');
            img.src = imageSrc instanceof Blob ? URL.createObjectURL(imageSrc) : imageSrc;
            img.classList.add('w-full', 'max-w-md', 'rounded-lg', 'shadow-md');
            elements.previewContainer.appendChild(img);
            elements.submitBtn.classList.remove('hidden');
        }

        async function fetchWithTimeout(url, options, timeout) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout);

            try {
                const response = await fetch(url, {
                    ...options,
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                return response;
            } catch (error) {
                clearTimeout(timeoutId);
                throw error;
            }
        }

        function displayResults(data) {
            // Prediction Display
            elements.predictionText.innerHTML = `
                <p class="font-semibold text-lg">${data.prediction?.predicted_label || 'Unknown Food'}</p>
                <div class="mt-2 space-y-1">
                    ${(data.prediction?.probabilities || [])
                        .map((prob, i) => `
                            <div class="flex justify-between">
                                <span>${['Fried Potatoes', 'Fried Rice', 'Pizza', 'Ice Cream'][i]}:</span>
                                <span>${(prob * 100).toFixed(2)}%</span>
                            </div>
                        `).join('')}
                </div>
            `;

            // Nutrition Display
            elements.nutritionText.innerHTML = data.nutrition?.error ? `
                <div class="text-red-500">${data.nutrition.error}</div>
            ` : `
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 bg-blue-50 rounded">
                        <p class="font-semibold">Calories</p>
                        <p>${data.nutrition?.calories || 'N/A'}</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded">
                        <p class="font-semibold">Protein</p>
                        <p>${data.nutrition?.protein || 'N/A'}</p>
                    </div>
                    <div class="p-3 bg-yellow-50 rounded">
                        <p class="font-semibold">Carbs</p>
                        <p>${data.nutrition?.total_carbohydrate?.value || 'N/A'}</p>
                    </div>
                    <div class="p-3 bg-red-50 rounded">
                        <p class="font-semibold">Fat</p>
                        <p>${data.nutrition?.total_fat?.value || 'N/A'}</p>
                    </div>
                </div>
            `;

            elements.resultDiv.classList.remove('hidden');
        }

        function handleApiError(error) {
            let message = 'Analysis failed. Please try again.';

            if (error.name === 'AbortError') {
                message = 'Request timed out. Please check your connection.';
            } else if (error.message.includes('Failed to fetch')) {
                message = 'Connection error. Ensure the server is running.';
            }

            elements.errorAlert.textContent = `${message} (${error.message})`;
            elements.errorAlert.classList.remove('hidden');
        }

        function showLoading(show) {
            elements.loader.classList.toggle('hidden', !show);
            elements.submitBtn.classList.toggle('hidden', show);
        }

        function hideError() {
            elements.errorAlert.classList.add('hidden');
        }
    </script>
</body>
</html>
