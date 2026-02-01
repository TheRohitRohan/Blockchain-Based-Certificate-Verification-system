# Certificate Verification System - Test Suite

This directory contains comprehensive tests for all components of the Certificate Verification System.

## 📁 Test Files

### 🔧 **Core System Tests**
1. **`test_database.php`** - Database connection and schema verification
2. **`test_blockchain.php`** - Blockchain integration and smart contract interaction  
3. **`test_pdf_generation.php`** - PDF creation, template rendering, and file storage

### 🔐 **Authentication & Security Tests**
4. **`test_authentication.php`** - User authentication, JWT tokens, role-based access
5. **`test_certificate_service.php`** - Certificate CRUD operations, verification, blockchain sync

### 🌐 **API Integration Tests**  
6. **`test_api_endpoints.php`** - All HTTP endpoints, request/response validation

### 🚀 **Test Execution**
7. **`run_all_tests.php`** - Complete test suite runner with comprehensive reporting

---

## 🧪 Running Tests

### **Individual Tests**
```bash
cd backend
php tests/test_database.php          # Test database connectivity
php tests/test_blockchain.php          # Test blockchain integration
php tests/test_pdf_generation.php      # Test PDF generation
php tests/test_authentication.php      # Test authentication system
php tests/test_certificate_service.php  # Test certificate services
php tests/test_api_endpoints.php       # Test API endpoints
```

### **Complete Test Suite**
```bash
cd backend
php tests/run_all_tests.php          # Run all tests with report
```

---

## 📊 Test Coverage

### **✅ Database Layer**
- Connection validation
- Table structure verification  
- Foreign key constraints
- Index presence
- Data integrity

### **✅ Blockchain Layer**
- Contract ABI loading
- Transaction signing
- Certificate issuance
- Hash generation/verification
- Configuration validation

### **✅ PDF Generation**
- mPDF library integration
- Template rendering
- File storage
- QR code generation
- Placeholder replacement

### **✅ Authentication**
- User login/logout
- JWT token generation/validation
- Role-based access control
- Password hashing/security
- User registration

### **✅ Certificate Service**
- Certificate creation
- Verification logic
- Student certificates
- Revocation process
- Blockchain synchronization

### **✅ API Endpoints**
- HTTP response codes
- Request/response format
- Authentication middleware
- CORS headers
- Error handling

---

## 🔍 Test Requirements

### **Prerequisites**
1. ✅ MySQL database running (certificate_db)
2. ✅ Ganache blockchain running (for blockchain tests)
3. ✅ mPDF library installed (via Composer)
4. ✅ Backend server running (for API tests)
5. ✅ Proper file permissions for storage

### **Configuration Files**
- `backend/config.php` - Database, blockchain, JWT settings
- `backend/database/schema.sql` - Database structure
- `backend/templates/certificate_template.html` - PDF template

---

## 📈 Expected Results

### **🟢 Healthy System** (95-100% pass rate)
- All components operational
- Ready for production
- Minor optimizations optional

### **🟡 Functional System** (80-94% pass rate)  
- Core features working
- Minor issues present
- Review recommended

### **🔴 Problematic System** (<80% pass rate)
- Critical issues detected
- Not production ready
- Immediate attention required

---

## 🐛 Troubleshooting

### **Database Test Failures**
- Check MySQL service status
- Verify database credentials
- Import schema.sql if tables missing

### **Blockchain Test Failures**
- Start Ganache: `ganache-cli --deterministic`
- Update contract address in config.php
- Verify RPC URL (default: http://127.0.0.1:7545)

### **PDF Test Failures**
- Install mPDF: `composer require mpdf/mpdf`
- Check storage directory permissions
- Verify template file exists

### **API Test Failures**
- Start backend server
- Check PHP error logs
- Verify database connection

---

## 📝 Test Data

### **Default Credentials**
- **Admin**: admin / admin123
- **Test Student**: john_student / student123 (created by tests)

### **Sample Certificate**
- Certificate ID: TEST-{unique-id}
- University: Demo University (DEMO-UNI-001)
- Course: Blockchain Development Fundamentals

---

## 🔄 Continuous Testing

### **Before Each Test Run**
1. Start MySQL database
2. Start Ganache blockchain
3. Start backend server
4. Clear browser cache (for frontend tests)

### **After Test Fixes**
1. Re-run individual test file
2. Re-run complete test suite
3. Verify no regressions
4. Update documentation

---

## 📞 Support

### **Test Suite Issues**
1. Check PHP version (>= 7.4 required)
2. Verify all dependencies installed
3. Review error messages in output
4. Check file permissions

### **Performance Monitoring**
- Monitor test execution time
- Track database query performance
- Monitor blockchain transaction times
- Measure PDF generation speed

---

**Last Updated**: December 2024
**System Version**: Certificate Verification System v1.0