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

    // Disable submit button to prevent double submissions
    var submitButton = currentForm.find("#submit");
    submitButton.prop("disabled", true);
    
    currentForm.find(".spinner").show();
    const email = currentForm.find("#email-input");
    const first_name = currentForm.find("#first_name-input");
    const last_name = currentForm.find("#last_name-input");
    const phone = currentForm.find("#phone-input");
    const description = currentForm.find("textarea#description-input");
    const linkedInUrl = currentForm.find("#LinkedInUrl");
    const companyAddressSelect = currentForm.find("#companyAddressId option:selected");
    const multiplePipeline = currentForm.find("#selectedPipelineId option:selected").val();
    const multipleStageId = currentForm.find("#selectedPipelineId option:selected").attr(
      "stageId"
    );
    const file = currentForm.find("input.file-upload")[0].files[0];
    const terms = currentForm.find("#terms");
    const availableFrom = currentForm.find("#availableFrom");

    var formData = new FormData();
    if (terms[0].checked) {
      currentForm.find("#terms-validate").hide();
      var errorMessages = validateRequiredInputs();
      if (errorMessages.length === 0) {
        formData.append("files", file);
        formData.append("firstName", first_name.val());
        formData.append("lastName", last_name.val());
        formData.append("email", email.val());
        formData.append("phone", phone.val());
        formData.append("description", description.val());
        formData.append("linkedInUrl", linkedInUrl.val());
        formData.append("state", 0);
        formData.append("currencyId", currencyId);

        if (pipelineId) {
          formData.append("pipelineId", pipelineId);
        } else if (multiplePipeline) {
          formData.append("pipelineId", multiplePipeline);
        }

        if (pipelineStageId) {
          formData.append("pipelineStageId", pipelineStageId);
        } else if (multipleStageId) {
          formData.append("pipelineStageId", multipleStageId);
        }

        if (recruitmentManagerId) {
          formData.append("recruitmentManagerId", recruitmentManagerId);
        }
        if (teamId) {
          formData.append("teamId", teamId);
        }
        if (companyAddressId) {
          formData.append("companyAddressId", companyAddressId);
        } else if (companyAddressSelect.length == 1) {
          formData.append("companyAddressId", companyAddressSelect.val());
        } else {
          formData.append("companyAddressId", "");
        }

        if (availableFrom.length > 0) {
          formData.append("availableFrom", availableFrom.val());
        }

        if (recruitmentSourceId) {
          formData.append("recruitmentSourceId", recruitmentSourceId);
        }
        formData.append("campaignCode", campaignCode);


        submitRequest(formData, currentForm);
      } else {
        currentForm.find(".spinner").hide();
        // Re-enable submit button if validation fails
        currentForm.find("#submit").prop("disabled", false);
      }
    } else {
      currentForm.find("#terms-validate").show();
      currentForm.find(".spinner").hide();
      // Re-enable submit button if terms not accepted
      currentForm.find("#submit").prop("disabled", false);
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

      return errorMessage;
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
        success: function (response) {
          if (response.response.code == 201) {
            currentForm.find(".spinner").hide();
            currentForm.find("#successful-submit-msg").show(300);
            setTimeout(function () {
              currentForm.find("#successful-submit-msg").fadeOut("fast");
            }, 5000);
            currentForm.find("input:checkbox").removeAttr("checked");
            currentForm.find(":input").not(":button, :submit, :reset, :hidden").val("");
            currentForm.find("#file-name").text("");
            // Re-enable submit button after successful submission
            currentForm.find("#submit").prop("disabled", false);
          } else if (response.response.code == 401) {
            currentForm.find("#unsuccessful-submit-msg").show(300);
            setTimeout(function () {
              currentForm.find("#unsuccessful-submit-msg").fadeOut("fast");
            }, 6000);
            currentForm.find(".spinner").hide();
            // Re-enable submit button after error
            currentForm.find("#submit").prop("disabled", false);
          }else{
            currentForm.find("#unsuccessful-submit-msg").show(300);
            setTimeout(function () {
              currentForm.find("#unsuccessful-submit-msg").fadeOut("fast");
            }, 6000);
            currentForm.find(".spinner").hide();
            // Re-enable submit button after error
            currentForm.find("#submit").prop("disabled", false);
          }
        },
        error: function (xhr, status, error) {
          // Handle AJAX errors (network issues, server errors, etc.)
          currentForm.find(".spinner").hide();
          currentForm.find("#unsuccessful-submit-msg").show(300);
          setTimeout(function () {
            currentForm.find("#unsuccessful-submit-msg").fadeOut("fast");
          }, 6000);
          // Re-enable submit button after error
          currentForm.find("#submit").prop("disabled", false);
        },
      });
    }
  });

});
