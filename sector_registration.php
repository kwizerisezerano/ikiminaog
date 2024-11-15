<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ikimina MIS Registration</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11">
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
            color: green;
            font-size: 1.2rem;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            pointer-events: none;
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

<div class="container shadow p-4 bg-white rounded" id="registrationFormContainer">
    <h2 class="form-header">IKIMINA MIS</h2>
    <p class="form-subheader">Create a new account<br>It's quick and easy.</p>

    <div class="error-text" id="registration-error" style="display: none;"></div>

    <form id="registrationForm" action="sector_registration_process.php" method="POST">
        <div class="form-row">
            <div class="form-group col-md-6">
                <input type="text" class="form-control" name="firstname" id="firstname" placeholder="Firstname" required>
                <small class="error-text" id="firstname-error"></small>
                <span class="valid-icon" id="firstname-valid">✔</span>
            </div>
            <div class="form-group col-md-6">
                <input type="text" class="form-control" name="lastname" id="lastname" placeholder="Lastname" required>
                <small class="error-text" id="lastname-error"></small>
                <span class="valid-icon" id="lastname-valid">✔</span>
            </div>
        </div>
        <div class="form-group">
            <input type="text" class="form-control" name="phone_number" id="phone_number" placeholder="Phone Number" maxlength="15" required>
            <small class="error-text" id="phone_number-error"></small>
            <span class="valid-icon" id="phone_number-valid">✔</span>
        </div>
        <div class="form-group">
            <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
            <small class="error-text" id="password-error"></small>
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            <span class="valid-icon" id="password-valid">✔</span>
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

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

            $.ajax({
                url: $(this).attr('action'),
                type: $(this).attr('method'),
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: response.message,
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                        }).then(() => {
                           // Redirect to verify.php with phone number
            const phoneNumber = $('#phone_number').val().trim();
            window.location.href = 'verify.php?phone_number=' + encodeURIComponent(phoneNumber);
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred.',
                    });
                }
            });
        });
    });
</script>
</body>
</html>
