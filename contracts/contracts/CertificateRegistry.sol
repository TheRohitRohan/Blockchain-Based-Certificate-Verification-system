// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

contract CertificateRegistry {
    struct Certificate {
        string certificateId;
        string studentName;
        string universityName;
        string courseName;
        string issueDate;
        string certificateHash;
        bool isValid;
        bool isRevoked;
        address issuedBy;
        uint256 timestamp;
    }

    mapping(string => Certificate) public certificates;
    mapping(address => bool) public authorizedIssuers;
    address public admin;
    
    event CertificateIssued(
        string indexed certificateId,
        string certificateHash,
        address indexed issuer,
        uint256 timestamp
    );
    
    event CertificateRevoked(
        string indexed certificateId,
        address indexed revokedBy,
        uint256 timestamp
    );
    
    event CertificateValidated(
        string indexed certificateId,
        bool isValid
    );

    modifier onlyAdmin() {
        require(msg.sender == admin, "Only admin can perform this action");
        _;
    }

    modifier onlyAuthorizedIssuer() {
        require(
            authorizedIssuers[msg.sender] || msg.sender == admin,
            "Not authorized to issue certificates"
        );
        _;
    }

    constructor() {
        admin = msg.sender;
        authorizedIssuers[msg.sender] = true;
    }

    function addAuthorizedIssuer(address issuer) public onlyAdmin {
        authorizedIssuers[issuer] = true;
    }

    function removeAuthorizedIssuer(address issuer) public onlyAdmin {
        authorizedIssuers[issuer] = false;
    }

    function issueCertificate(
        string memory certificateId,
        string memory studentName,
        string memory universityName,
        string memory courseName,
        string memory issueDate,
        string memory certificateHash
    ) public onlyAuthorizedIssuer {
        require(
            bytes(certificates[certificateId].certificateId).length == 0,
            "Certificate ID already exists"
        );

        certificates[certificateId] = Certificate({
            certificateId: certificateId,
            studentName: studentName,
            universityName: universityName,
            courseName: courseName,
            issueDate: issueDate,
            certificateHash: certificateHash,
            isValid: true,
            isRevoked: false,
            issuedBy: msg.sender,
            timestamp: block.timestamp
        });

        emit CertificateIssued(certificateId, certificateHash, msg.sender, block.timestamp);
    }

    function verifyCertificate(string memory certificateId, string memory certificateHash)
        public
        view
        returns (bool)
    {
        Certificate memory cert = certificates[certificateId];
        
        if (bytes(cert.certificateId).length == 0) {
            return false; // Certificate doesn't exist
        }
        
        if (cert.isRevoked) {
            return false; // Certificate has been revoked
        }
        
        if (keccak256(bytes(cert.certificateHash)) != keccak256(bytes(certificateHash))) {
            return false; // Hash mismatch
        }
        
        return cert.isValid;
    }

    function getCertificate(string memory certificateId)
        public
        view
        returns (
            string memory studentName,
            string memory universityName,
            string memory courseName,
            string memory issueDate,
            string memory certificateHash,
            bool isValid,
            bool isRevoked,
            address issuedBy,
            uint256 timestamp
        )
    {
        Certificate memory cert = certificates[certificateId];
        require(bytes(cert.certificateId).length > 0, "Certificate does not exist");
        
        return (
            cert.studentName,
            cert.universityName,
            cert.courseName,
            cert.issueDate,
            cert.certificateHash,
            cert.isValid,
            cert.isRevoked,
            cert.issuedBy,
            cert.timestamp
        );
    }

    function revokeCertificate(string memory certificateId) public onlyAdmin {
        require(
            bytes(certificates[certificateId].certificateId).length > 0,
            "Certificate does not exist"
        );
        
        certificates[certificateId].isRevoked = true;
        certificates[certificateId].isValid = false;
        
        emit CertificateRevoked(certificateId, msg.sender, block.timestamp);
    }
}

