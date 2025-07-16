# Vulnerable Online Course Platform

⚠️ **WARNING: This application contains intentional security vulnerabilities and should NEVER be used in production!**

## Overview

This is a deliberately vulnerable online course platform built with PHP native, MySQL, and Bootstrap. It's designed for educational purposes to help security professionals, developers, and students learn about web application security vulnerabilities and how to exploit them.

## Features

### Authentication
- Login using phone number and OTP (sent to email)
- Role-based access (Member/Company)
- Session management

### Member Features
- Dashboard with course statistics
- Profile management with file upload
- Course browsing and enrollment
- Payment confirmation via file upload
- Access to purchased course materials

### Company Features
- Company dashboard with analytics
- Course management (create, edit, delete)
- Member enrollment management
- Payment approval/rejection

## Intentional Vulnerabilities

This application includes the following security vulnerabilities for educational purposes:

### 1. SQL Injection
- All database queries are vulnerable to SQL injection
- No prepared statements or input sanitization
- Direct concatenation of user input in SQL queries

### 2. Cross-Site Scripting (XSS)
- Reflected XSS in search parameters
- Stored XSS in user-generated content
- No input/output encoding

### 3. Broken Access Control
- Insecure Direct Object References (IDOR)
- Missing authorization checks
- Privilege escalation possibilities

### 4. Broken Authentication
- Weak session management
- Session fixation vulnerabilities
- No session regeneration

### 5. File Upload Vulnerabilities
- No file type validation
- No file size restrictions
- Directory traversal possible
- Executable file uploads allowed

### 6. Security Misconfiguration
- Debug mode enabled in production
- Detailed error messages exposed
- Permissive file permissions
- Missing security headers

### 7. Cross-Site Request Forgery (CSRF)
- No CSRF tokens on forms
- State-changing operations via GET requests

### 8. Information Disclosure
- Database errors exposed to users
- Debug information visible
- Sensitive configuration exposed

## Setup Instructions

### Using Docker (Recommended)

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd vulnerable-online-course
   ```

2. Start the application:
   ```bash
   docker-compose up -d
   ```

3. Access the application:
   - Main application: http://localhost:8004
   - phpMyAdmin: http://localhost:9004
   - MySQL: localhost:3306

4. Default credentials:
   - Company account: 081234567890
   - Member account: 081234567891

### Manual Setup

1. Requirements:
   - PHP 8.0+
   - MySQL 8.0+
   - Apache/Nginx web server

2. Database setup:
   - Create database: `online_course`
   - Import: `database/init.sql`

3. Configuration:
   - Update `config/env.php` with your database credentials

4. Web server configuration:
   - Point document root to project directory
   - Ensure `.htaccess` is enabled (Apache)

## Security Testing

### SQL Injection Examples

```sql
-- Login bypass
Phone: 081234567890' OR '1'='1' --

-- Union-based injection
Search: ' UNION SELECT username,password FROM admin_users --

-- Boolean-based blind injection
Search: ' AND (SELECT COUNT(*) FROM users) > 0 --
```

### XSS Examples

```javascript
// Reflected XSS
URL: /?search=<script>alert('XSS')</script>

// Stored XSS in profile
Name: <img src=x onerror=alert('XSS')>
```

### File Upload Exploitation

```php
// PHP webshell
<?php system($_GET['cmd']); ?>

// Directory traversal
../../etc/passwd
```

### IDOR Examples

```
// Access other user's courses
/pages/member/course-detail.php?id=999

// View other company's data
/pages/company/dashboard.php?company_id=1
```

## Learning Objectives

By using this vulnerable application, you will learn:

1. How to identify and exploit common web vulnerabilities
2. The impact of security flaws on web applications
3. How attackers think and operate
4. Why secure coding practices are essential
5. How to perform security testing and penetration testing

## Educational Use

This application is intended for:
- Security training and education
- Penetration testing practice
- Secure code review training
- Understanding OWASP Top 10 vulnerabilities
- Security awareness demonstrations

## Security Remediation

For each vulnerability in this application, consider:

1. **SQL Injection**: Use prepared statements, input validation, and parameterized queries
2. **XSS**: Implement proper output encoding and Content Security Policy
3. **Access Control**: Implement proper authorization checks and principle of least privilege
4. **File Upload**: Validate file types, implement size limits, and store files securely
5. **CSRF**: Use CSRF tokens and proper state management
6. **Session Management**: Implement secure session handling and regeneration

## Important Notes

- **DO NOT** deploy this application to production environments
- **DO NOT** use this code as a basis for real applications
- **ALWAYS** practice ethical hacking and responsible disclosure
- Use this application only in controlled, isolated environments
- Ensure you have proper authorization before testing

## Legal Disclaimer

This application is for educational purposes only. The authors are not responsible for any misuse of this software. Users are solely responsible for ensuring they have proper authorization before testing any systems.

## Contribution

If you find additional vulnerabilities or have suggestions for improvement, please:
1. Create detailed documentation of the vulnerability
2. Provide proof-of-concept exploits
3. Submit a pull request with clear explanations

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [PortSwigger Web Security Academy](https://portswigger.net/web-security)
- [SANS Secure Coding Practices](https://www.sans.org/white-papers/2172/)

---

Remember: The goal is to learn and improve security, not to cause harm. Always practice ethical hacking! 🛡️
