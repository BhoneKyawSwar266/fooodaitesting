<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flask API Integration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 via-purple-100 to-pink-100 font-sans min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8 max-w-lg">
        <h1 class="text-4xl font-extrabold text-center text-gray-800 mb-8 bg-gradient-to-r from-blue-500 to-purple-500 text-transparent bg-clip-text">Food Recognition</h1>

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
            <form id="upload-form" action="{{ route('predict') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" id="image-input" name="image" accept="image/*" required class="hidden">
                <button type="button" id="choose-file-btn" class="w-full sm:w-auto bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105">
                    Choose File
                </button>
                <div id="preview-container" class="mt-4 flex flex-col items-center"></div>
                <button type="submit" id="submit-btn" class="hidden w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105 mt-4">
                    Upload Image
                </button>
            </form>
        </div>

        <!-- Result Section -->
        <div id="result" class="hidden bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Prediction Result</h2>
            <p id="prediction-text" class="text-gray-600 whitespace-pre-wrap bg-gray-50 p-4 rounded-lg"></p>
            <h2 class="text-2xl font-semibold text-gray-700 mt-6 mb-4">Nutrition Facts</h2>
            <div id="nutrition-text" class="text-gray-600 bg-gray-50 p-4 rounded-lg"></div>
        </div>
    </div>

    <script>
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
        let stream;

        startCameraBtn.addEventListener('click', async () => {
            try {
                const constraints = {
                    video: {
                        facingMode: { ideal: 'user' }, // Prefer front camera
                        width: { ideal: 1280 }, // Optional: improve quality
                        height: { ideal: 720 }
                    }
                };
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                cameraPreview.srcObject = stream;
                cameraPreview.classList.remove('hidden');
                captureBtn.classList.remove('hidden');
                chooseFileBtn.classList.add('hidden');
            } catch (error) {
                console.error('Camera error:', error);
                alert('Error accessing camera: ' + error.message);
            }
        });

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

            canvas.toBlob((blob) => {
                const file = new File([blob], 'capture.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                imageInput.files = dataTransfer.files;

                const previewImg = document.createElement('img');
                previewImg.src = URL.createObjectURL(blob);
                previewImg.classList.add('w-full', 'max-w-md', 'mx-auto', 'rounded-lg', 'shadow-md');

                previewContainer.innerHTML = '';
                previewContainer.appendChild(previewImg);
                previewContainer.appendChild(submitBtn);
                submitBtn.classList.remove('hidden');
            }, 'image/jpeg');
        });

        chooseFileBtn.addEventListener('click', () => {
            imageInput.click();
        });

        imageInput.addEventListener('change', () => {
            if (imageInput.files.length > 0) {
                const previewImg = document.createElement('img');
                previewImg.src = URL.createObjectURL(imageInput.files[0]);
                previewImg.classList.add('w-full', 'max-w-md', 'mx-auto', 'rounded-lg', 'shadow-md');

                previewContainer.innerHTML = '';
                chooseFileBtn.classList.add('hidden');
                previewContainer.appendChild(previewImg);
                previewContainer.appendChild(submitBtn);
                submitBtn.classList.remove('hidden');
            }
        });

        document.getElementById('upload-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            try {
                const response = await fetch('{{ route("predict") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (!response.ok) throw new Error('API request failed');
                const data = await response.json();

                if (data.error) {
                    predictionText.textContent = 'Error: ' + data.error;
                    nutritionText.innerHTML = '';
                } else {
                    const prediction = data.prediction;
                    const labels = ['fried_potatoes', 'fried_rice', 'pizza', 'ice_cream'];
                    predictionText.textContent = `Predicted: ${prediction.predicted_label}\nProbabilities:\n${labels.map((label, i) => `${label}: ${(prediction.probabilities[i] * 100).toFixed(2)}%`).join('\n')}`;

                    const nutrition = data.nutrition;
                    if (nutrition.error) {
                        nutritionText.textContent = nutrition.error;
                    } else {
                        nutritionText.innerHTML = `
                            <p><strong>${nutrition.food_name}</strong> (${nutrition.serving_size})</p>
                            <p>Calories: ${nutrition.calories}</p>
                            <p>Calorie Breakdown: ${nutrition.calorie_breakdown}</p>
                            <p>Total Fat: ${nutrition.total_fat.value} (${nutrition.total_fat.dv})</p>
                            <p>Saturated Fat: ${nutrition.saturated_fat.value} (${nutrition.saturated_fat.dv})</p>
                            <p>Cholesterol: ${nutrition.cholesterol.value} (${nutrition.cholesterol.dv})</p>
                            <p>Sodium: ${nutrition.sodium.value} (${nutrition.sodium.dv})</p>
                            <p>Total Carbohydrate: ${nutrition.total_carbohydrate.value} (${nutrition.total_carbohydrate.dv})</p>
                            <p>Protein: ${nutrition.protein}</p>
                        `;
                    }
                }
                resultDiv.classList.remove('hidden');
            } catch (error) {
                predictionText.textContent = 'Error: ' + error.message;
                nutritionText.innerHTML = '';
                resultDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
