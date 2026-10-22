var $ = jQuery.noConflict()
var index = '';

jQuery(document).ready(function ($) {
  
  var isFormClickable = true;

  $(".cinode-form").click(function () {
    if (!isFormClickable) {
      return; 
    }

    var clickedForm = $(this);
    index = $(".cinode-form").index(clickedForm);

    isFormClickable = false;

    setTimeout(function () {
      isFormClickable = true;
    }, 1000); 
  });


  $(".cinode-form #file-upload").change(function () {
    var fileName = $(".cinode-form:eq("+index+") #file-upload").prop("files")[0].name;

    var fileText = $(".cinode-form").eq(index).find(".file-name");
    fileText.text(fileName);
  
    setTimeout(function () {
      isFormClickable = true;
    }, 1000); 
  });

  $('.cinode-form').submit(function (event) {
    event.preventDefault();
    var currentForm = $(this);

    currentForm.find(".spinner").show();
    const email = currentForm.find("#email-input");
    const first_name = currentForm.find("#first_name-input");
    const last_name = currentForm.find("#last_name-input");
    const phone = currentForm.find("#phone-input");
    const description = currentForm.find("textarea#description-input");
    const linkedInUrl = currentForm.find("#LinkedInUrl");
    const companyAddressSelect = currentForm.find("#companyAddressId option:selected");
    const multiplePipeline = currentForm.find("#selectedPipelineId option:selected").val();
    const fileInput = currentForm.find("input.file-upload");
    const file = fileInput[0].files[0];
    const terms = currentForm.find("#terms");
    const availableFrom = currentForm.find("#availableFrom");

    var formData = new FormData();
    if (terms[0].checked) {
      currentForm.find("#terms-validate").hide();
      var errorMessages = validateRequiredInputs();
      if (errorMessages.length === 0) {
        if (file) {
          formData.append("files", file);
        }
        formData.append("firstName", first_name.val());
        formData.append("lastName", last_name.val());
        formData.append("email", email.val());
        formData.append("phone", phone.val());
        formData.append("description", description.val());
        formData.append("linkedInUrl", linkedInUrl.val());
        formData.append("formConfig", currentForm.find("form").attr("data-cinode-config") || "");
        formData.append("captchaResponse", captchaResponse());

        if (multiplePipeline) {
          formData.append("selectedPipelineId", multiplePipeline);
        }

        if (companyAddressSelect.length == 1) {
          formData.append("companyAddressId", companyAddressSelect.val());
        }

        if (availableFrom.length > 0) {
          formData.append("availableFrom", availableFrom.val());
        }

        submitRequest(formData, currentForm);
      } else {
        currentForm.find(".spinner").hide();
      }
    } else {
      currentForm.find("#terms-validate").show();
      currentForm.find(".spinner").hide();
    }

    function captchaResponse() {
      var token = "";
      currentForm
        .find('[name="g-recaptcha-response"], [name="h-captcha-response"], [name="cf-turnstile-response"]')
        .each(function () {
          if (!token && $(this).val()) {
            token = $(this).val();
          }
        });
      return token;
    }

    function fileIsValid() {
      if (!file) {
        return true;
      }
      var allowed = (fileInput.attr("accept") || "").toLowerCase().split(",");
      var extension = "." + file.name.split(".").pop().toLowerCase();
      var maxSize = parseInt(fileInput.attr("data-max-size"), 10) || 0;
      return allowed.indexOf(extension) !== -1 && (!maxSize || file.size <= maxSize);
    }

    function validateRequiredInputs() {
      var errorMessage = [];

      if (email.val() === "") {
        currentForm.find("#email-required").show();
        errorMessage.push("Aplicant email is missing");
      } else {
        errorMessage = errorMessage.filter(
          (message) => message !== "Aplicant email is missing"
        );
        currentForm.find("#email-required").hide();
      }
      var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
      if (!emailReg.test(email.val())) {
        currentForm.find("#email-required").show();
      }

      if (first_name.val() === "") {
        errorMessage.push("First Name is missing");
        currentForm.find("#first_name-required").show();
      } else {
        errorMessage = errorMessage.filter((message) => message !== "First Name is missing");
        currentForm.find("#first_name-required").hide();
      }
      if (last_name.val() === "") {
        errorMessage.push("Last Name is missing");
        currentForm.find("#last_name-required").show();
      } else {
        errorMessage = errorMessage.filter((message) => message !== "Last Name is missing");
        currentForm.find("#last_name-required").hide();
      }

      if (fileIsValid()) {
        currentForm.find("#file-invalid").hide();
      } else {
        errorMessage.push("Invalid file");
        currentForm.find("#file-invalid").show();
      }

      return errorMessage;
    }

    function resetCaptcha() {
      try {
        if (window.grecaptcha && typeof window.grecaptcha.reset === "function") {
          window.grecaptcha.reset();
        }
      } catch (e) {
        // Invisible/v3 captchas have no widget to reset.
      }
    }

    function showError() {
      currentForm.find("#unsuccessful-submit-msg").show(300);
      setTimeout(function () {
        currentForm.find("#unsuccessful-submit-msg").fadeOut("fast");
      }, 6000);
      currentForm.find(".spinner").hide();
      resetCaptcha();
    }

    function submitRequest(data, currentForm) {
      currentForm.find(".spinner").show();
      var url = cinode_url.site_url;
      var path = url + "/wp-json/cinode/v2/cinode-recruitment";

      $.ajax({
        type: "POST",
        url: path,
        data: data,
        contentType: false,
        processData: false,
        success: function (response, status, xhr) {
          if (xhr.status === 201 && response && response.success) {
            currentForm.find(".spinner").hide();
            currentForm.find("#successful-submit-msg").show(300);
            setTimeout(function () {
              currentForm.find("#successful-submit-msg").fadeOut("fast");
            }, 5000);
            currentForm.find("input:checkbox").removeAttr("checked");
            currentForm.find(":input").not(":button, :submit, :reset, :hidden").val("");
            currentForm.find(".file-name").text("");
            resetCaptcha();
          } else {
            showError();
          }
        },
        error: function () {
          showError();
        },
      });
    }
  });

});
