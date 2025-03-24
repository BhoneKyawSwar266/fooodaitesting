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
        <h1 class="text-4xl font-extrabold text-center text-gray-800 mb-8 bg-gradient-to-r from-blue-500 to-purple-500 text-transparent bg-clip-text">Food Recognition AI</h1>

        <!-- Camera Section -->
        <div class="camera-section mb-8 bg-white p-6 rounded-xl shadow-lg">
            <button id="start-camera" class="w-full sm:w-auto bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105">
                Open Camera
            </button>
            <video id="camera-preview" class="hidden w-full max-w-md mx-auto border-2 border-gray-200 rounded-lg mt-4" autoplay playsinline></video>
            <button id="capture-btn" class="hidden w-full sm:w-auto bg-gradient-to-r from-green-500 to-teal-600 hover:from-green-600 hover:to-teal-700 text-white font-semibold py-3 px-6 rounded-lg mt-4 transition duration-300 transform hover:scale-105">
                Capture Image
            </button>
        </div>

        <!-- Upload Section -->
        <div class="upload-section mb-8 bg-white p-6 rounded-xl shadow-lg">
            <form id="upload-form" class="flex flex-col">
                <input type="file" id="image-input" name="image" accept="image/*" required class="hidden">
                <button type="button" id="choose-file-btn" class="w-full sm:w-auto bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105">
                    Choose File
                </button>
                <div id="preview-container" class="mt-4 flex flex-col items-center"></div>
                <button type="submit" id="submit-btn" class="hidden w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105 mt-4">
                    Analyze Image
                </button>
                <div id="loader" class="hidden mt-4">
                    <div class="loader"></div>
                    <p class="text-center mt-2 text-gray-600">Analyzing your food...</p>
                </div>
            </form>
        </div>

        <!-- Result Section -->
        <div id="result" class="hidden bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Prediction Result</h2>
            <p id="prediction-text" class="text-gray-600 whitespace-pre-wrap bg-gray-50 p-4 rounded-lg"></p>
            <h2 class="text-2xl font-semibold text-gray-700 mt-6 mb-4">Nutrition Facts</h2>
            <div id="nutrition-text" class="text-gray-600 bg-gray-50 p-4 rounded-lg"></div>
            <button id="try-again-btn" class="w-full mt-4 bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
                Try Another Image
            </button>
        </div>
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
        const tryAgainBtn = document.getElementById('try-again-btn');
        let stream;

        // API Configuration
        const API_ENDPOINT = 'https://24.144.117.151:5000/predict';
        const CSRF_TOKEN = '{{ csrf_token() }}';

        // Camera Setup
        startCameraBtn.addEventListener('click', async () => {
            try {
                if (stream) stream.getTracks().forEach(track => track.stop());

                const constraints = {
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: { ideal: 'environment' }
                    }
                };

                stream = await navigator.mediaDevices.getUserMedia(constraints)
                    .catch(() => navigator.mediaDevices.getUserMedia({ video: true }));

                cameraPreview.srcObject = stream;
                cameraPreview.classList.remove('hidden');
                captureBtn.classList.remove('hidden');
                chooseFileBtn.classList.add('hidden');
            } catch (error) {
                alert(`Camera Error: ${error.message}`);
                console.error('Camera Error:', error);
            }
        });

        // Capture Image
        captureBtn.addEventListener('click', () => {
            const canvas = document.createElement('canvas');
            canvas.width = cameraPreview.videoWidth;
            canvas.height = cameraPreview.videoHeight;
            canvas.getContext('2d').drawImage(cameraPreview, 0, 0, canvas.width, canvas.height);

            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                cameraPreview.srcObject = null;
                cameraPreview.classList.add('hidden');
                captureBtn.classList.add('hidden');
            }

            canvas.toBlob(blob => {
                const file = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                imageInput.files = dataTransfer.files;
                showPreview(blob);
            }, 'image/jpeg', 0.95);
        });

        // File Selection
        chooseFileBtn.addEventListener('click', () => imageInput.click());
        imageInput.addEventListener('change', () => {
            if (imageInput.files.length > 0) {
                showPreview(URL.createObjectURL(imageInput.files[0]));
            }
        });

        // Show Image Preview
        function showPreview(imageSrc) {
            const previewImg = document.createElement('img');
            previewImg.src = imageSrc instanceof Blob ? URL.createObjectURL(imageSrc) : imageSrc;
            previewImg.classList.add('w-full', 'max-w-md', 'mx-auto', 'rounded-lg', 'shadow-md', 'mb-4');

            previewContainer.innerHTML = '';
            previewContainer.appendChild(previewImg);
            submitBtn.classList.remove('hidden');
        }

        // Form Submission
        document.getElementById('upload-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            // Show loading state
            submitBtn.classList.add('hidden');
            loader.classList.remove('hidden');
            resultDiv.classList.add('hidden');

            try {
                const formData = new FormData();
                formData.append('image', imageInput.files[0]);
                formData.append('_token', CSRF_TOKEN);

                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                    credentials: 'omit'
                });

                if (!response.ok) {
                    throw new Error(`Server responded with ${response.status}`);
                }

                const data = await response.json();
                displayResults(data);
            } catch (error) {
                console.error('API Error:', error);
                predictionText.textContent = `Error: ${error.message}\n\nPlease ensure:`;
                nutritionText.innerHTML = `
                    <ul class="list-disc pl-5">
                        <li>The prediction server is running</li>
                        <li>You're using a valid HTTPS connection</li>
                        <li>The image contains recognizable food</li>
                    </ul>
                `;
                resultDiv.classList.remove('hidden');
            } finally {
                loader.classList.add('hidden');
            }
        });

        // Display Results
        function displayResults(data) {
            if (data.error) {
                predictionText.textContent = 'Error: ' + data.error;
                nutritionText.innerHTML = '';
            } else {
                const prediction = data.prediction;
                const labels = ['fried_potatoes', 'fried_rice', 'pizza', 'ice_cream'];
                predictionText.textContent = `Predicted: ${prediction.predicted_label}\n\nConfidence:\n${labels.map((label, i) => `• ${label}: ${(prediction.probabilities[i] * 100).toFixed(2)}%`).join('\n')}`;

                const nutrition = data.nutrition;
                nutritionText.innerHTML = nutrition.error ? nutrition.error : `
                    <div class="space-y-2">
                        <p><span class="font-semibold">Food:</span> ${nutrition.food_name} (${nutrition.serving_size})</p>
                        <p><span class="font-semibold">Calories:</span> ${nutrition.calories}</p>
                        <p><span class="font-semibold">Macros:</span> ${nutrition.calorie_breakdown}</p>
                        <div class="grid grid-cols-2 gap-2 mt-3">
                            <div class="bg-blue-50 p-2 rounded"><span class="font-semibold">Fat:</span> ${nutrition.total_fat.value} (${nutrition.total_fat.dv})</div>
                            <div class="bg-green-50 p-2 rounded"><span class="font-semibold">Carbs:</span> ${nutrition.total_carbohydrate.value} (${nutrition.total_carbohydrate.dv})</div>
                            <div class="bg-yellow-50 p-2 rounded"><span class="font-semibold">Protein:</span> ${nutrition.protein}</div>
                            <div class="bg-red-50 p-2 rounded"><span class="font-semibold">Sodium:</span> ${nutrition.sodium.value} (${nutrition.sodium.dv})</div>
                        </div>
                    </div>
                `;
            }
            resultDiv.classList.remove('hidden');
        }

        // Try Again Button
        tryAgainBtn.addEventListener('click', () => {
            resultDiv.classList.add('hidden');
            previewContainer.innerHTML = '';
            imageInput.value = '';
            chooseFileBtn.classList.remove('hidden');
            startCameraBtn.classList.remove('hidden');
        });
    </script>
</body>
</html>
