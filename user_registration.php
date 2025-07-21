<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ikimina MIS Registration</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .container {
            max-width: 400px;
            margin-top: 10px;
        }
        .form-header {
            text-align: center;
            color: #007bff;
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
            color: #007bff !important; /* Force blue color */
            font-size: 1.2rem;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            pointer-events: none;
        }
        
        /* Additional rule to ensure all Font Awesome icons are blue */
        .fas.fa-check.valid-icon {
            color: #007bff !important;
        }
        .toggle-password {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #007bff;
            display: none;
            z-index: 1;
        }
        .valid-icon.show {
            display: inline;
        }
        a {
            text-decoration: none;
            color: #007bff;
            transition: color 0.3s ease;
        }
        a:hover {
            color: #0056b3;
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

        /* Enhanced loading modal styles */
        .success-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .success-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-icon {
            width: 80px;
            height: 80px;
            border: 4px solid #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: checkmark 0.6s ease-in-out;
        }

        .success-icon i {
            color: #28a745;
            font-size: 40px;
        }

        @keyframes checkmark {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        .success-title {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }

        .success-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .countdown-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .countdown-text {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .countdown-display {
            font-size: 3rem;
            font-weight: bold;
            color: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .spinner-border {
            width: 30px;
            height: 30px;
        }

        .redirect-info {
            font-size: 0.9rem;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="terms-modal" id="termsModal">
    <div class="modal-content">
        <span class="close-modal" id="closeModal">&times;</span>
        <h5>Terms and Conditions</h5>
        <p>
            After registration, users can log in and update their profiles with additional details
            before they can apply to join an ikimina. By checking the checkbox above, you agree to our Terms. 
            The system ensures phone number uniqueness and accuracy. Once verified, you can update profiles, manage contributions, 
            and apply for loans in an ikimina.
        </p>
    </div>
</div>

<div class="d-flex justify-content-center align-items-center min-vh-100 bg-light">
    <div class="container shadow p-4 bg-white rounded" id="registrationFormContainer">
        <h2 class="form-header">IKIMINA MIS</h2>
        <p class="form-subheader">Register your account</p>

        <div class="error-text" id="registration-error" style="display: none;"></div>

        <form id="registrationForm" action="user_registration_process.php" method="POST">
            <div class="form-group">
                <input type="text" class="form-control" name="firstname" id="firstname" placeholder="Firstname" required>
                <small class="error-text" id="firstname-error"></small>
                <i class="fas fa-check valid-icon" id="firstname-valid"></i>         
            </div>
            <div class="form-group">
                <input type="text" class="form-control" name="lastname" id="lastname" placeholder="Lastname" required>
                <small class="error-text" id="lastname-error"></small>
                <i class="fas fa-check valid-icon" id="lastname-valid"></i>
            </div>
            <div class="form-group">
                <input type="text" class="form-control" name="phone_number" id="phone_number" placeholder="Phone Number" maxlength="15" required>
                <small class="error-text" id="phone_number-error"></small>
                <i class="fas fa-check valid-icon" id="phone_number-valid"></i>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                <small class="error-text" id="password-error"></small>
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                <i class="fas fa-check valid-icon" id="password-valid"></i>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="terms" name="terms">
                <label class="form-check-label checkbox-text" for="terms">I agree to the <a href="#" id="termsLink">Terms and Conditions</a>.</label>
                <small class="error-text" id="terms-error"></small>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>Sign Up</button>
        </form>

        <p class="form-footer mt-3">Already have an Account? <a href="index.php">Log In</a></p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    function validateField(field, regex, errorMessage) {
        const value = field.val().trim();
        const errorField = $("#" + field.attr("id") + "-error");
        const validIcon = $("#" + field.attr("id") + "-valid");

        if (regex.test(value)) {
            errorField.text("").hide();
            validIcon.addClass("show");
            return true;
        } else {
            errorField.text(errorMessage).show();
            validIcon.removeClass("show");
            return false;
        }
    }

    function showSuccessModal(message, phoneNumber) {
        const modalHTML = `
            <div class="success-modal" id="successModal">
                <div class="success-content">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="success-title">Success!</div>
                    <div class="success-message">${message}</div>
                    <div class="countdown-container">
                        <div class="countdown-text">Redirecting to verification page in:</div>
                        <div class="countdown-display">
                            <div class="spinner-border text-primary" role="status"></div>
                            <span id="countdownNumber">5</span>
                        </div>
                    </div>
                    <div class="redirect-info">Please wait while we redirect you...</div>
                </div>
            </div>
        `;
        
        $('body').append(modalHTML);
        
        let countdown = 5;
        const countdownElement = $('#countdownNumber');
        
        const interval = setInterval(() => {
            countdown--;
            countdownElement.text(countdown);
            
            if (countdown <= 0) {
                clearInterval(interval);
                window.location.href = 'verify.php?phone_number=' + encodeURIComponent(phoneNumber);
            }
        }, 1000);
    }

    $(document).ready(function() {
        const namePattern = /^[a-zA-Z\s]+$/;
        const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
        const phonePattern = /^\d{10,15}$/;

        $('#terms').change(function() {
            $('#submitBtn').prop('disabled', !this.checked);
        });

        $('#firstname').on('input', function() {
            validateField($(this), namePattern, "Firstname must only contain letters and spaces.");
        });

        $('#lastname').on('input', function() {
            validateField($(this), namePattern, "Lastname must only contain letters and spaces.");
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
                    submitBtn.text('Sign Up');
                    
                    if (response.error) {
                        // Show error with simple alert - no SweetAlert2
                        alert('Registration Failed: ' + response.message);
                    } else {
                        // Show custom success modal with countdown - NO SweetAlert2
                        const phoneNumber = $('#phone_number').val().trim();
                        showSuccessModal(response.message, phoneNumber);
                    }
                },
                error: function(xhr, status, error) {
                    // Remove loading state
                    submitBtn.removeClass('loading btn-loading').prop('disabled', false);
                    submitBtn.text('Sign Up');
                    
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
                    
                    // Show error with simple alert - no SweetAlert2
                    alert('Connection Error: ' + errorMessage);
                }
            });
        });
    });
</script>
</body>
</html>