var $ = jQuery.noConflict();

jQuery(document).ready(function ($) {

  // File name display – scoped to the form that owns the file input.
  // Uses event delegation so it works even when forms are added dynamically.
  $(document).on('change', '.cinode-form input.file-upload', function () {
    var files = this.files;
    var fileName = files && files[0] ? files[0].name : '';
    $(this).closest('.cinode-form').find('.file-name').text(fileName);
  });

  $('.cinode-form').submit(function (event) {
    event.preventDefault();
    var currentForm = $(this);

    // ----------------------------------------------------------------
    // Read per-form configuration from data-* attributes written by the
    // PHP shortcode.  This allows multiple shortcodes on the same page
    // to each carry their own independent settings without relying on
    // globally-scoped JS variables that would be overwritten.
    // ----------------------------------------------------------------
    var pipelineId           = parseInt(currentForm.data('pipeline-id') || 0, 10);
    var pipelineStageId      = parseInt(currentForm.data('pipeline-stage-id') || 0, 10);
    var recruitmentManagerId = parseInt(currentForm.data('recruitment-manager-id') || 0, 10);
    var teamId               = parseInt(currentForm.data('team-id') || 0, 10);
    var companyAddressId     = parseInt(currentForm.data('company-address-id') || 0, 10);
    var recruitmentSourceId  = parseInt(currentForm.data('recruitment-source-id') || 0, 10);
    var campaignCode         = currentForm.data('campaign-code') || '';
    var currencyId           = parseInt(currentForm.data('currency-id') || 1, 10);
    var recipientType        = currentForm.data('recipient-type') || 'candidate';
    var cvRequired           = currentForm.data('cv-required') === true;
    var parseCV              = currentForm.data('parse-cv') === true;
    var autoSubcontractorGroupIds = currentForm.data('auto-subcontractor-group-ids') || [];

    currentForm.find('.spinner').show();

    // Field references – use name/type attributes so duplicate IDs across
    // multiple shortcode instances on the same page are never an issue.
    var email       = currentForm.find("input[name='email']");
    var first_name  = currentForm.find("input[name='first_name']");
    var last_name   = currentForm.find("input[name='last_name']");
    var phone       = currentForm.find("input[name='phone']");
    var description = currentForm.find("textarea[name='Description']");
    var linkedInUrl = currentForm.find("input[name='LinkedInUrl']");
    var companyAddressSelect = currentForm.find("select[name='companyAddressId'] option:selected");
    var multiplePipeline = currentForm.find("select[name='selectedPipelineId'] option:selected").val();
    var multipleStageId  = currentForm.find("select[name='selectedPipelineId'] option:selected").attr('stageId');
    var file        = currentForm.find('input.file-upload')[0].files[0];
    var terms       = currentForm.find("input[name='terms']");
    var availableFrom = currentForm.find("input[name='availableFrom']");
    var gender      = currentForm.find("select[name='gender']");

    // Collect subcontractor group IDs (checkboxes or single select).
    var subcontractorGroupIds = [];
    currentForm.find('input[name="subcontractorGroupIds[]"]:checked').each(function () {
      subcontractorGroupIds.push($(this).val());
    });
    var groupSelect = currentForm.find('select[name="subcontractorGroupIds[]"]');
    if (groupSelect.length > 0 && groupSelect.val()) {
      subcontractorGroupIds.push(groupSelect.val());
    }

    var formData = new FormData();
    if (terms[0].checked) {
      currentForm.find('.cinode-terms-validate').hide();
      var errorMessages = validateRequiredInputs();
      if (errorMessages.length === 0) {
        if (file) {
          formData.append('files', file);
        }
        formData.append('firstName', first_name.val());
        formData.append('lastName', last_name.val());
        formData.append('email', email.val());
        formData.append('phone', phone.val());
        formData.append('description', description.val());
        formData.append('linkedInUrl', linkedInUrl.val());
        formData.append('state', 0);
        formData.append('currencyId', currencyId);
        formData.append('recipient_type', recipientType);
        formData.append('parse_cv', parseCV ? '1' : '0');

        if (recipientType === 'subcontractor') {
          formData.append('gender', gender.length > 0 ? gender.val() : '0');
          // User-selected groups (from visible selector).
          subcontractorGroupIds.forEach(function (id) {
            if (id) { formData.append('subcontractorGroupIds[]', id); }
          });
          // Auto-join groups set in admin/shortcode (no visible selector).
          autoSubcontractorGroupIds.forEach(function (id) {
            formData.append('subcontractorGroupIds[]', id);
          });
        }

        // Subcontractors cannot be placed into pipelines.
        if (recipientType !== 'subcontractor') {
          if (pipelineId) {
            formData.append('pipelineId', pipelineId);
          } else if (multiplePipeline) {
            formData.append('pipelineId', multiplePipeline);
          }

          if (pipelineStageId) {
            formData.append('pipelineStageId', pipelineStageId);
          } else if (multipleStageId) {
            formData.append('pipelineStageId', multipleStageId);
          }
        }

        if (recruitmentManagerId) {
          formData.append('recruitmentManagerId', recruitmentManagerId);
        }
        if (teamId) {
          formData.append('teamId', teamId);
        }
        if (companyAddressId) {
          formData.append('companyAddressId', companyAddressId);
        } else if (companyAddressSelect.length === 1) {
          formData.append('companyAddressId', companyAddressSelect.val());
        } else {
          formData.append('companyAddressId', '');
        }

        if (availableFrom.length > 0) {
          formData.append('availableFrom', availableFrom.val());
        }

        if (recruitmentSourceId) {
          formData.append('recruitmentSourceId', recruitmentSourceId);
        }
        formData.append('campaignCode', campaignCode);

        submitRequest(formData, currentForm);
      } else {
        currentForm.find('.spinner').hide();
      }
    } else {
      currentForm.find('.cinode-terms-validate').show();
      currentForm.find('.spinner').hide();
    }

    function validateRequiredInputs() {
      var errorMessage = [];

      var emailReg =
        /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

      if (email.val() === '' || !emailReg.test(email.val())) {
        currentForm.find('.cinode-email-required').show();
        errorMessage.push('Applicant email is missing or invalid');
      } else {
        currentForm.find('.cinode-email-required').hide();
      }

      if (first_name.val() === '') {
        errorMessage.push('First Name is missing');
        currentForm.find('.cinode-firstname-required').show();
      } else {
        currentForm.find('.cinode-firstname-required').hide();
      }

      if (last_name.val() === '') {
        errorMessage.push('Last Name is missing');
        currentForm.find('.cinode-lastname-required').show();
      } else {
        currentForm.find('.cinode-lastname-required').hide();
      }

      // CV required validation.
      if (cvRequired && !file) {
        currentForm.find('.cinode-file-required').show();
        errorMessage.push('CV file is required');
      } else {
        currentForm.find('.cinode-file-required').hide();
      }

      // Gender required for subcontractor mode.
      if (recipientType === 'subcontractor' && gender.length > 0 && (gender.val() === null || gender.val() === '')) {
        currentForm.find('.cinode-gender-required').show();
        errorMessage.push('Gender is required');
      } else {
        currentForm.find('.cinode-gender-required').hide();
      }

      return errorMessage;
    }

    function submitRequest(data, currentForm) {
      currentForm.find('.spinner').show();
      var path = cinode_url.site_url + '/wp-json/cinode/v2/cinode-recruitment';

      $.ajax({
        type: 'POST',
        url: path,
        data: data,
        contentType: false,
        processData: false,
        success: function (response) {
          if (response.status === 201) {
            currentForm.find('.spinner').hide();
            currentForm.find('.cinode-success-msg').show(300);
            setTimeout(function () {
              currentForm.find('.cinode-success-msg').fadeOut('fast');
            }, 5000);
            currentForm.find('input:checkbox').removeAttr('checked');
            currentForm.find(':input').not(':button, :submit, :reset, :hidden').val('');
            currentForm.find('.file-name').text('');
          } else {
            currentForm.find('.cinode-error-msg').show(300);
            setTimeout(function () {
              currentForm.find('.cinode-error-msg').fadeOut('fast');
            }, 6000);
            currentForm.find('.spinner').hide();
          }
        },
      });
    }
  });

});
