<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IZU MIS - Member Registration</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>

        .image-upload-container {
            text-align: center;
            margin-top: 20px;
        }

        .preview {
            display: none; /* Hidden by default */
            margin-top: 10px;
            width: 150px;
            height: 50px;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .close-preview {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #f00;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 14px;
            cursor: pointer;
        }

        input[type="file"] {
            margin-top: 5px;
            display: block;
        }

        body {
            background-color: #f7f9fc;
            font-family: Arial, sans-serif;
        }

        .container {
        margin-top: 0px;
        padding-top: 0px;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
         
        }

        .card-header {
            background-color: #00a3e0;
            color: white;
            font-weight: bold;
            font-size: 1rem;
            padding: 5px;
            text-align: center;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: -30px;
        }

        .input-group-text {
            background-color: #00a3e0;
            color: white;
            border: none;
            padding: 4px 8px;
        }

        .btn-primary {
            background-color: #00a3e0;
            border: none;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            padding: 4px;
            font-weight: bold;
        }

        .btn-primary:hover {
            background-color: #008bb8;
            box-shadow: 0 3px 8px rgba(0, 163, 224, 0.3);
        }

        .form-control, #file-input {
            border-radius: 6px;
            padding: 0px 10px;
            border-color: #ced4da;
            /* margin-top: -5px; */
        }

        .form-control:focus {
            border-color: #00a3e0;
            box-shadow: 0 0 4px rgba(0, 163, 224, 0.3);
        }
        .form-group {
    margin-bottom: 0px; /* Reduces space between form groups */
}

.form-row {
    margin-bottom: 10px; /* Reduces space between rows */
}

    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Tontine Identification</div>
                    <div class="card-body">
                        <form id="registrationForm" method="POST" autocomplete="off">
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label for="tontineName">Tontine Name *</label>
                                    <input type="text" class="form-control" id="tontineName" name="tontineName" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Upload Image</label>
                                    <input type="file" id="file-input" accept="image/*">
                                    <div class="preview" id="image-preview">
                                        <button type="button" class="close-preview" onclick="removeImagePreview()">x</button>
                                        <img src="#" alt="Image Preview">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label for="join_date">Join Date *</label>
                                    <input type="date" class="form-control" id="join_date" name="join_date" readonly>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="province">Province *</label>
                                    <select id="province" name="province" class="form-control" required>
                                        <option value="">Select Province</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label for="district">District *</label>
                                    <select id="district" name="district" class="form-control" required>
                                        <option value="">Select District</option>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="sector">Sector *</label>
                                    <select id="sector" name="sector" class="form-control" required>
                                        <option value="">Select Sector</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label for="cell">Cell *</label>
                                    <select id="cell" name="cell" class="form-control" required>
                                        <option value="">Select Cell</option>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="village">Village *</label>
                                    <select id="village" name="village" class="form-control" required>
                                        <option value="">Select Village</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label for="membersList">List of Members or Representatives *</label>
                                    <textarea class="form-control" id="membersList" name="membersList" required></textarea>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="contributions">Total Paid Up Contributions *</label>
                                    <input type="number" class="form-control" id="contributions" name="contributions" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-6 form-group">
                                    <label for="representatives">Representatives *</label>
                                    <textarea class="form-control" id="representatives" name="representatives" required></textarea>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="rules">Rules of Procedure *</label>
                                    <textarea class="form-control" id="rules" name="rules" required></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block mt-2"><i class="fas fa-save"></i> Register</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card page-preview">
                    <div class="card-header">Tontine Page Preview</div>
                    <div class="card-body">
                        <div class="company-logo">
                            <img src="https://via.placeholder.com/100" alt="Tontine Logo" id="logoPreview">
                        </div>
                        <h5 id="previewName">Tontine Name</h5>
                        <p id="previewSector">District-Sector: Kigali - Rwanda</p>
                        <p id="previewPurpose">Purpose: A community fund for social support.</p>
                        <a href="#" class="btn btn-primary">Visit Tontine Page</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

  

                          
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
          <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="rwanda.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const fileInput = document.getElementById('file-input');
        const imagePreview = document.getElementById('image-preview');

        // Set initial state of preview to hidden
        imagePreview.style.display = 'none';

        fileInput.addEventListener('change', function() {
            const file = fileInput.files[0];

            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Set the image src to the selected file's data
                    imagePreview.innerHTML = `<button type="button" class="close-preview" onclick="removeImagePreview()">x</button><img src="${event.target.result}" alt="Image Preview">`;
                    imagePreview.style.display = 'flex'; // Show the preview
                    fileInput.style.display = 'none'; // Hide the file input
                };
                reader.readAsDataURL(file);
            }
        });

        function removeImagePreview() {
            imagePreview.style.display = 'none'; // Hide the preview
            fileInput.style.display = 'block';   // Show the file input again
            fileInput.value = '';                // Clear the file input value
        }
    </script>
</body>
</html>
