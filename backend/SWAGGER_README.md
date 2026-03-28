# Swagger/OpenAPI Documentation

This directory contains complete API documentation for the Certificate Verification System using the OpenAPI 3.0 specification.

## Files

- **`openapi.yaml`** - Complete OpenAPI 3.0 specification with all endpoints, schemas, and security definitions
- **`swagger-ui.html`** - Standalone Swagger UI HTML file for viewing and testing the API

## Quick Start

### Option 1: Using Swagger UI Locally (Recommended)

1. **Open the Swagger UI**
   - Simply open `swagger-ui.html` in your browser
   - Or use a local server if you prefer:
     ```bash
     # Using PHP built-in server
     php -S localhost:8000
     
     # Using Python 3
     python -m http.server 8000
     
     # Using Node.js http-server
     npx http-server
     ```
   - Then navigate to `http://localhost:8000/swagger-ui.html`

2. **Test API Endpoints**
   - Expand any endpoint to see details
   - Click "Try it out"
   - Fill in required parameters/body
   - Click "Execute" to test
   - View the response

### Option 2: Using Swagger Editor Online

1. Go to https://editor.swagger.io/
2. File → Import File → select `openapi.yaml`
3. Test endpoints directly from the browser

### Option 3: Using API Tools

**Postman:**
1. Open Postman
2. File → Import → Upload Files → select `openapi.yaml`
3. All endpoints will be imported with proper structure

**Insomnia:**
1. Open Insomnia
2. Create workspace
3. Import → From File → select `openapi.yaml`
4. Test endpoints

## API Base URL

- **Development:** `http://localhost/api`
- **Production:** `https://api.certificate-system.com`

## Authentication

Most endpoints require JWT Bearer token authentication.

### Getting a Token

1. **Login Endpoint:** `POST /auth/login`
   ```json
   {
     "email": "admin@certificate-system.com",
     "password": "admin123"
   }
   ```
   
2. **Response:**
   ```json
   {
     "success": true,
     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
     "user": {
       "id": 1,
       "email": "admin@certificate-system.com",
       "role": "admin",
       "full_name": "Admin User"
     }
   }
   ```

3. **Use Token in Requests:**
   - In Swagger UI: Click "Authorize" button and paste the token
   - In Postman: Add header `Authorization: Bearer <token>`
   - In Insomnia: Add header `Authorization: Bearer <token>`

## Example Workflows

### Workflow 1: Create and Verify a Certificate

1. **Login** → Get JWT token
2. **Create Certificate** (POST `/certificates/create`)
   - Requires: student_id, course_name, issue_date
3. **Verify Certificate** (POST `/certificates/verify`)
   - Use returned certificate_id
4. **Download Certificate** (GET `/certificates/download`)
   - Use certificate_id parameter

### Workflow 2: Public Verification

1. **Verify Publicly** (GET/POST `/public/verify`)
   - No authentication required
   - Pass certificate_id as parameter
2. **Download Publicly** (GET `/public/certificate/download`)
   - No authentication required
   - Returns PDF for viewing/download

## API Endpoints Summary

### Authentication
- `POST /auth/login` - User login
- `POST /auth/register` - User registration

### Certificates (Authenticated)
- `POST /certificates/create` - Create new certificate
- `POST /certificates/upload` - Upload existing certificate
- `POST /certificates/verify` - Verify certificate
- `GET /certificates` - Get user's certificates
- `GET /certificates/get` - Get specific certificate
- `GET /certificates/list` - List with pagination & filters
- `GET /certificates/download` - Download certificate PDF
- `PUT /certificates/update` - Update certificate
- `POST /certificates/delete` - Delete certificate
- `POST /certificates/revoke` - Revoke certificate

### Public Verification (No Auth)
- `GET|POST /public/verify` - Public certificate verification
- `GET /public/certificate/download` - Public certificate download

### Universities (Authenticated)
- `GET /universities` - List all universities
- `POST /universities` - Create university (admin only)
- `POST /universities/generate-key` - Generate key pair (admin only)

### Students (Authenticated)
- `GET /students` - List students
- `POST /students` - Create new student

## Roles and Permissions

| Endpoint | Admin | University | Student |
|----------|:-----:|:----------:|:-------:|
| /auth/login | ✓ | ✓ | ✓ |
| /auth/register | ✓ | ✓ | ✓ |
| /certificates/create | ✓ | ✓ | ✗ |
| /certificates/upload | ✓ | ✓ | ✗ |
| /certificates/verify | ✓ | ✓ | ✓ |
| /certificates/revoke | ✓ | ✓ | ✗ |
| /certificates/delete | ✓ | ✗ | ✗ |
| /students (all) | ✓ | ✓ | ✗ |
| /universities (all) | ✓ | ✗ | ✗ |
| /public/* | ✗ | ✗ | ✗ |

## Default Test Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@certificate-system.com | admin123 |
| University Staff | university@mit.edu | password123 |
| Student | student@example.com | password123 |

## Response Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request successful |
| 201 | Created - Resource created |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Missing/invalid token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 500 | Server Error - Internal error |

## Common Response Patterns

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { }
}
```

### Error Response
```json
{
  "error": "Error message",
  "code": 400
}
```

## Troubleshooting

### CORS Issues
If you get CORS errors:
- Check that API is running on `localhost`
- Frontend URL should be in allowed origins list
- Ensure `Access-Control-Allow-Origin` headers are configured

### Token Expired
- Re-login to get a new token
- Clear browser cache if needed

### File Upload Errors
- Ensure file size is within limits
- Use multipart/form-data content-type
- Check file is valid PDF

## Additional Resources

- [OpenAPI 3.0 Specification](https://spec.openapis.org/oas/v3.0.0)
- [Swagger UI Documentation](https://swagger.io/tools/swagger-ui/)
- [Bearer Token Authentication](https://tools.ietf.org/html/rfc6750)

---

**Last Updated:** 2024
**API Version:** 1.0.0
