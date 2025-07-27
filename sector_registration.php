<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ikimina MIS Sector Registration</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .swal2-confirm:focus {
            outline: none !important;
            box-shadow: none !important;
        }

        .container {
            max-width: 450px;
            margin-top: 10px;
        }
        
        .form-header {
            text-align: center;
            color: #007bff !important;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .form-subheader {
            text-align: center;
            color: gray;
            font-size: 0.9rem;
        }
        
        .form-footer {
            text-align: center;
            font-size: 0.8rem;
            color: gray;
        }
        
        .checkbox-text {
            font-size: 0.9rem;
            color: #555;
        }
        
        .error-text {
            color: red;
            font-size: 0.8rem;
            margin-top: 5px;
            display: none;
        }
        
        .form-group {
            position: relative;
        }
        
        .valid-icon {
            color: #007bff !important;
            font-size: 1.2rem;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            pointer-events: none;
        }
        
        .fas.fa-check.valid-icon,
        .valid-icon.fas.fa-check {
            color: #007bff !important;
        }
        
        .toggle-password {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #007bff !important;
            display: none;
            z-index: 1;
        }
        
        .valid-icon.show {
            display: inline;
        }
        
        a {
            text-decoration: none;
            color: #007bff !important;
            transition: color 0.3s ease;
        }
        
        a:hover {
            color: #0056b3 !important;
            text-decoration: underline;
        }
        
        .terms-modal {
            display: none;
            position: fixed;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            max-width: 400px;
            z-index: 1000;
            padding: 20px;
            border-radius: 5px;
            background-color: #ffffff;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-content {
            padding: 20px;
            text-align: center;
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            color: red;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        #registrationFormContainer.modal-active {
            opacity: 0.5;
        }
        
        /* Loading spinner styles */
        .loading {
            position: relative;
            pointer-events: none;
        }
        
        .loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            margin: -12px 0 0 -12px;
            width: 24px;
            height: 24px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 1000;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn-loading {
            color: transparent !important;
        }

        /* Primary button styling */
        .btn-primary {
            background-color: #007bff !important;
            border-color: #007bff !important;
        }
        
        .btn-primary:hover {
            background-color: #0056b3 !important;
            border-color: #0056b3 !important;
        }

        /* SweetAlert2 custom styling */
        .swal2-popup {
            border-radius: 15px !important;
        }
        
        .swal2-confirm {
            background-color: #007bff !important;
            border: none !important;
        }
        
        .swal2-confirm:hover {
            background-color: #0056b3 !important;
        }
        
        .swal2-icon.swal2-success {
            border-color: #007bff !important;
            color: #007bff !important;
        }
        
        .swal2-icon.swal2-success [class^='swal2-success-line'] {
            background-color: #007bff !important;
        }
        
        .swal2-icon.swal2-success .swal2-success-ring {
            border-color: #007bff !important;
        }
        
        .swal2-icon.swal2-error {
            border-color: #dc3545 !important;
            color: #dc3545 !important;
        }

        /* Row styling for side-by-side fields */
        .form-row .form-group {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="terms-modal" id="termsModal">
    <div class="modal-content">
        <span class="close-modal" id="closeModal">&times;</span>
        <h5>Terms and Conditions</h5>
        <p>
            After registration, sector representatives can log in and verify user applications for joining ikimina groups.
            By checking the checkbox above, you agree to our Terms. The system ensures phone number uniqueness and accuracy.
            Once verified, you can manage verification requests and oversee ikimina operations in your sector.
        </p>
    </div>
</div>

<div class="d-flex justify-content-center align-items-center min-vh-100 bg-light">
    <div class="container shadow p-4 bg-white rounded" id="registrationFormContainer">
        <h2 class="form-header">IKIMINA MIS</h2>
        <p class="form-subheader">Sector Representative Registration</p>

        <div class="error-text" id="registration-error" style="display: none;"></div>

        <form id="registrationForm" action="sector_registration_process.php" method="POST">
           
                <div class="form-group">
                    <input type="text" class="form-control" name="firstname" id="firstname" placeholder="Firstname" required autocomplete="off">
                    <small class="error-text" id="firstname-error"></small>
                    <i class="fas fa-check valid-icon" id="firstname-valid"></i>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="lastname" id="lastname" placeholder="Lastname" required autocomplete="off">
                    <small class="error-text" id="lastname-error"></small>
                    <i class="fas fa-check valid-icon" id="lastname-valid"></i>
                </div>

            
            <div class="form-group">
                <input type="text" class="form-control" name="phone_number" id="phone_number" placeholder="Phone Number" maxlength="15" required autocomplete="off">
                <small class="error-text" id="phone_number-error"></small>
                <i class="fas fa-check valid-icon" id="phone_number-valid"></i>
            </div>
            
            <div class="form-group">
                <input type="password" class="form-control" name="password" id="password" placeholder="Password" required autocomplete="off">
                <small class="error-text" id="password-error"></small>
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                <i class="fas fa-check valid-icon" id="password-valid"></i>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="terms" name="terms">
                <label class="form-check-label checkbox-text" for="terms">I agree to the <a href="#" id="termsLink">Terms and Conditions</a>.</label>
                <small class="error-text" id="terms-error"></small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>Register as Sector Representative</button>
        </form>

        <p class="form-footer mt-3">Already have an Account? <a href="index.php">Log In</a></p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    function validateField(field, regex, errorMessage, minLength = null) {
        const value = field.val().trim();
        const errorField = $("#" + field.attr("id") + "-error");
        const validIcon = $("#" + field.attr("id") + "-valid");

        let isValid = regex.test(value);
        
        // Check minimum length if specified
        if (isValid && minLength !== null && value.length < minLength) {
            isValid = false;
        }

        if (isValid) {
            errorField.text("").hide();
            validIcon.addClass("show");
            return true;
        } else {
            errorField.text(errorMessage).show();
            validIcon.removeClass("show");
            return false;
        }
    }

    function showSuccessAlert(message, phoneNumber) {
        let timerInterval;
        Swal.fire({
            title: 'Registration Successful!',
            html: `
                <div style="text-align: center;">
                    <p style="margin-bottom: 20px; font-size: 16px; color: #666;">${message}</p>
                    <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 20px 0;">
                        <p style="margin-bottom: 10px; color: #6c757d;">Redirecting to verification page in:</p>
                        <div style="font-size: 48px; font-weight: bold; color: #007bff;">
                            <b></b>
                        </div>
                    </div>
                    <p style="font-size: 14px; color: #6c757d; font-style: italic;">Please wait while we redirect you...</p>
                </div>
            `,
            timer: 5000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                const b = Swal.getHtmlContainer().querySelector('b');
                timerInterval = setInterval(() => {
                    b.textContent = Math.ceil(Swal.getTimerLeft() / 1000);
                }, 100);
            },
            willClose: () => {
                clearInterval(timerInterval);
            }
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.timer) {
                window.location.href = 'verify.php?phone_number=' + encodeURIComponent(phoneNumber);
            }
        });
    }

    function showErrorAlert(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonText: 'Try Again',
            confirmButtonColor: '#007bff'
        });
    }

    $(document).ready(function() {
        const namePattern = /^[a-zA-Z\s]+$/;
        const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
        const phonePattern = /^\d{10,15}$/;

        $('#terms').change(function() {
            $('#submitBtn').prop('disabled', !this.checked);
        });

        $('#firstname').on('input', function() {
            validateField($(this), namePattern, "Firstname must be at least 3 characters and contain only letters and spaces.", 3);
        });

        $('#lastname').on('input', function() {
            validateField($(this), namePattern, "Lastname must be at least 3 characters and contain only letters and spaces.", 3);
        });

        $('#phone_number').on('input', function() {
            validateField($(this), phonePattern, "Phone number must be between 10 and 15 digits.");
        });

        $('#password').on('input', function() {
            const isValid = validateField($(this), passwordPattern, "Password must be at least 8 characters long and include upper/lowercase letters, numbers, and special characters.");
            $('#togglePassword').toggle(isValid);
        });

        $('#togglePassword').on('click', function() {
            const passwordField = $('#password');
            const passwordType = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', passwordType);
            $(this).toggleClass('fa-eye fa-eye-slash');
        });

        $('#termsLink').on('click', function(e) {
            e.preventDefault();
            $('#termsModal').fadeIn();
            $('#registrationFormContainer').addClass("modal-active");
        });

        $('#closeModal').on('click', function() {
            $('#termsModal').fadeOut();
            $('#registrationFormContainer').removeClass("modal-active");
        });

        $(document).on('click', function(event) {
            if (!$(event.target).closest('#termsModal, #termsLink').length) {
                $('#termsModal').fadeOut();
                $('#registrationFormContainer').removeClass("modal-active");
            }
        });

        $('#registrationForm').on('submit', function(e) {
            e.preventDefault();
            
            // Add loading state
            const submitBtn = $('#submitBtn');
            submitBtn.addClass('loading btn-loading').prop('disabled', true);
            submitBtn.text('Processing...');

            $.ajax({
                url: $(this).attr('action'),
                type: $(this).attr('method'),
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    // Remove loading state
                    submitBtn.removeClass('loading btn-loading').prop('disabled', false);
                    submitBtn.text('Register as Sector Representative');
                    
                    if (response.error) {
                        // Show error with SweetAlert2
                        showErrorAlert('Registration Failed', response.message);
                    } else {
                        // Show success alert with countdown
                        const phoneNumber = $('#phone_number').val().trim();
                        showSuccessAlert(response.message, phoneNumber);
                    }
                },
                error: function(xhr, status, error) {
                    // Remove loading state
                    submitBtn.removeClass('loading btn-loading').prop('disabled', false);
                    submitBtn.text('Register as Sector Representative');
                    
                    let errorMessage = 'An unexpected error occurred.';
                    
                    // Try to get more specific error message
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            // If response is not JSON, check for common errors
                            if (xhr.status === 0) {
                                errorMessage = 'Connection failed. Please check your internet connection.';
                            } else if (xhr.status === 404) {
                                errorMessage = 'Registration service not found. Please contact support.';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Server error. Please try again later.';
                            } else {
                                errorMessage = `Server returned error ${xhr.status}: ${error}`;
                            }
                        }
                    }
                    
                    // Show error with SweetAlert2
                    showErrorAlert('Connection Error', errorMessage);
                }
            });
        });
    });
</script>
</body>
</html>