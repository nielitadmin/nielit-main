(function () {
    'use strict';

    function getConfig() {
        var base = window.facultyModuleConfig || {};
        return {
            dropdownId: base.dropdownId || 'edit_faculty',
            displayId: base.displayId || 'display_faculty',
            endpoint: base.endpoint || 'add_faculty_ajax.php',
            isMasterAdmin: !!base.isMasterAdmin
        };
    }

    function ensureModalExists() {
        if (document.getElementById('addFacultyModal')) {
            return;
        }

        var modalHTML = '' +
            '<div class="modal fade" id="addFacultyModal" tabindex="-1" aria-labelledby="addFacultyModalLabel" aria-hidden="true" ' +
            'style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050;">' +
                '<div class="modal-dialog" style="position: relative; margin: 50px auto; max-width: 500px;">' +
                    '<div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">' +
                        '<div class="modal-header" style="padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">' +
                            '<h5 class="modal-title" id="addFacultyModalLabel" style="margin: 0; font-size: 16px; font-weight: 600;">' +
                                '<i class="fas fa-user-plus"></i> Add New Faculty Member' +
                            '</h5>' +
                            '<button type="button" class="btn-close" onclick="closeAddFacultyModal()" aria-label="Close" ' +
                                    'style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>' +
                        '</div>' +
                        '<div class="modal-body" style="padding: 20px;">' +
                            '<form id="addFacultyForm">' +
                                '<div class="mb-3" style="margin-bottom: 15px;">' +
                                    '<label for="faculty_name" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Name *</label>' +
                                    '<input type="text" class="form-control" id="faculty_name" name="name" required ' +
                                           'placeholder="e.g., Dr. John Smith" ' +
                                           'style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">' +
                                '</div>' +
                                '<div class="mb-3" style="margin-bottom: 15px;">' +
                                    '<label for="faculty_email" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>' +
                                    '<input type="email" class="form-control" id="faculty_email" name="email" ' +
                                           'placeholder="e.g., john.smith@nielit.gov.in" ' +
                                           'style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">' +
                                '</div>' +
                                '<div class="mb-3" style="margin-bottom: 15px;">' +
                                    '<label for="faculty_phone" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Phone</label>' +
                                    '<input type="text" class="form-control" id="faculty_phone" name="phone" ' +
                                           'placeholder="e.g., 9876543210" ' +
                                           'style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">' +
                                '</div>' +
                                '<div class="mb-3" style="margin-bottom: 15px;">' +
                                    '<label for="faculty_designation" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Designation</label>' +
                                    '<input type="text" class="form-control" id="faculty_designation" name="designation" ' +
                                           'placeholder="e.g., Professor, Assistant Professor, Lecturer" ' +
                                           'style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">' +
                                '</div>' +
                                '<div class="mb-3" style="margin-bottom: 15px;">' +
                                    '<label for="faculty_department" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Department</label>' +
                                    '<input type="text" class="form-control" id="faculty_department" name="department" ' +
                                           'placeholder="e.g., Computer Science, Information Technology" ' +
                                           'style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">' +
                                '</div>' +
                            '</form>' +
                        '</div>' +
                        '<div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">' +
                            '<button type="button" class="btn btn-secondary" onclick="closeAddFacultyModal()" ' +
                                    'style="padding: 8px 16px; border: 1px solid #6c757d; background: #6c757d; color: white; border-radius: 4px; cursor: pointer;">Cancel</button>' +
                            '<button type="button" class="btn btn-success" onclick="addNewFaculty()" ' +
                                    'style="padding: 8px 16px; border: 1px solid #198754; background: #198754; color: white; border-radius: 4px; cursor: pointer;">' +
                                '<i class="fas fa-save"></i> Add Faculty' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function updateFacultyDisplay(config) {
        var select = document.getElementById(config.dropdownId);
        if (!select) {
            return;
        }

        var selectedOptions = Array.prototype.slice.call(select.selectedOptions || []);
        var displayElement = document.getElementById(config.displayId);

        if (!displayElement) {
            return;
        }

        if (selectedOptions.length === 0) {
            displayElement.textContent = 'To be assigned';
            return;
        }

        var facultyNames = selectedOptions.map(function (option) {
            return option.value;
        });
        displayElement.textContent = facultyNames.join(', ');
    }

    function openAddFacultyModal() {
        ensureModalExists();

        var form = document.getElementById('addFacultyForm');
        if (form) {
            form.reset();
        }

        var modal = document.getElementById('addFacultyModal');
        if (modal) {
            modal.style.display = 'block';
            modal.onclick = function (event) {
                if (event.target === modal) {
                    closeAddFacultyModal();
                }
            };
        }
    }

    function closeAddFacultyModal() {
        var modal = document.getElementById('addFacultyModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function addNewFaculty() {
        var config = getConfig();
        var form = document.getElementById('addFacultyForm');

        if (!form) {
            alert('Add Faculty form is not available. Please refresh the page.');
            return;
        }

        var formData = new FormData(form);
        var name = String(formData.get('name') || '').trim();
        if (!name) {
            alert('Faculty name is required!');
            return;
        }

        var actionButton = document.querySelector('#addFacultyModal .btn.btn-success');
        var originalText = actionButton ? actionButton.innerHTML : '';
        if (actionButton) {
            actionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            actionButton.disabled = true;
        }

        var payload = {
            action: 'add_faculty',
            name: name,
            email: String(formData.get('email') || '').trim(),
            phone: String(formData.get('phone') || '').trim(),
            designation: String(formData.get('designation') || '').trim(),
            department: String(formData.get('department') || '').trim()
        };

        fetch(config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (parseError) {
                        throw new Error('Server returned invalid response: ' + text.substring(0, 160));
                    }
                });
            })
            .then(function (result) {
                if (!result.success) {
                    throw new Error(result.message || 'Unknown error');
                }

                var facultySelect = document.getElementById(config.dropdownId);
                if (facultySelect && result.faculty) {
                    var newOption = document.createElement('option');
                    newOption.value = result.faculty.name;
                    newOption.setAttribute('data-id', result.faculty.id);
                    newOption.setAttribute('data-designation', result.faculty.designation || '');
                    newOption.setAttribute('data-can-delete', 'true');
                    newOption.selected = true;

                    var visibilityTag = config.isMasterAdmin ? ' [Global]' : ' [My Faculty]';
                    newOption.textContent = result.faculty.name +
                        (result.faculty.designation ? ' (' + result.faculty.designation + ')' : '') +
                        visibilityTag;

                    facultySelect.appendChild(newOption);

                    if (typeof window.updateFacultyField === 'function') {
                        window.updateFacultyField();
                    } else {
                        updateFacultyDisplay(config);
                    }
                }

                closeAddFacultyModal();
                if (typeof window.showToast === 'function') {
                    window.showToast(result.message || 'Faculty member added successfully!', 'success');
                }
            })
            .catch(function (error) {
                console.error('Error adding faculty:', error);
                alert('Error adding faculty: ' + error.message);
            })
            .finally(function () {
                if (actionButton) {
                    actionButton.innerHTML = originalText;
                    actionButton.disabled = false;
                }
            });
    }

    window.openAddFacultyModal = openAddFacultyModal;
    window.closeAddFacultyModal = closeAddFacultyModal;
    window.addNewFaculty = addNewFaculty;
})();
