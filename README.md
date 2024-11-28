# ssh_certificates
Implementation of SSH certificates like x509 hierarchy (multi-tier)

## Introduction
The following is to explain, in the simplest way possible, how to implement a multi-tier SSH certificate hierarchy.
This mini-project is what helped me to understand how certificates and a chain-of-trust works.
I hope that this helps others to not only understand PKI fundamentals a bit better, but also improve their overall security.
This project was completed using headless AntiX core Virtual Machines, but the principles will apply to all distributions. 


## Important Information
- Ensure ssh is installed on your server.
- Do NOT add public keys to the authorized_keys file (this will force Public Key Authentication instead of certificates)
- Do NOT say 'Yes' to fingerprints unless transferring files (remove the fingerprints setup)
- Once certificates are setup, deactivate password authentication in the sshd_config
- Ensure the chain of authority is copied over to the respective servers

### Common Commands (Syntax & Examples)
*SYNTAX:*
```
ssh-keygen -t <ALGORITHM> -b <BYTE_SIZE> -f <FILENAME> -N <PASSWORD>
ssh-keygen -s <PRIVATE_KEY> -I <CERTIFICATE_IDENTITY> -h -n <HOST_NAME> -V +<VALIDITY_PERIOD> <PUB_KEY_TO_SIGN>
ssh-keygen -s <PRIVATE_KEY> -I <CERTIFICATE_IDENTITY> -n <USER_NAME> -V <VALIDITY_PERIOD> <USER_PUB_KEY_TO_SIGN>
ssh-keygen -Lf <SIGNED_CERTIFICATE>
```
*EXAMPLES:*
```
//Create a 4096-bit RSA Key Pair with an Empty Password called 'ssh-root-ca'
//This creates: ssh-root-ca & ssh-root-ca.pub
ssh-keygen -t rsa -b 4096 -f ./ssh-root-ca -N ''

//Sign the ssh-sub-ca Host public key with the ssh-root-ca private key that is valid for 30 weeks
//The -I is the identity that will be shown on the ssh logs when used to sign in
//The -h is to signify a host certificate -n is the host's name
ssh-keygen -s ssh-root-ca -I sub-ca-machine-name -h -n ssh-sub-ca.example.com -V +30w sub-ca.pub

//Sign the user public key with the sub-ca private key
//Allow this certificate to authenticate as the std_user username on the server for a period of 10 weeks
ssh-keygen -s sub-ca-key -I std_user@exampleclient.com -n std_user -V +10w user-key.pub

//View the certificate's public key and the signature of the signing authority
//The principals denote the user or host trusted by the signing authority
ssh-keygen -Lf user-key-cert.pub
```

### Important Configurations
In **/etc/ssh/sshd_config**:
- *HostCertificate*
This option expects a path to the host machine's certificate. This is the certificate that will be presented to any client connecting to the server.
When connecting to a normal server for the first time, we are presented with a fingerprint. When a HostCertificate is set up, the fingerprint shown is the same Public Key seen when viewing the certificate with *ssh-keygen -Lf ssh-sub-ca-cert.pub*
- *HostKey*
This option expects a path to the host machine's private key. The private key here cannot, to my knowledge, have password protection.
This is the private key that will decrypt any data sent to it via the client's encrypted tunnel.
- *TrustedUserCAKeys*
This option expects a path to the public key of the signing authority. For example, if the user has a certificate signed by the sub-ca-key private key, this option would point to the sub-ca-key.pub public key. The sub-ca-key is a trusted authority and the user-key-cert.pub carries its signature. Therefore, the user has been authorized by the sub-ca-key

In **/etc/ssh/ssh_known_hosts**:
- This file must contain the certificate authority public keys including the principles that are authorized by those authorities.
- The line starts with "@cert-authority" string, followed by the principle signed by this authority. 
- Example: "@cert-authority ssh-root-ca.example.com,ssh-sub-ca.example.com ssh-rsa..."
- This denotes that the following public key is that of a **host certificate/certificate authority** who has **signed** the certificate of the following **host(s)**
- By having these CA public keys in this file, effectively your machine says *'Hey, I see a connection from ServerA. I know ServerA because I see its fingerprint in my database, so I trust ServerA'*

---

### Overview
What we are going to do in the following exercises is to establish an 'Anchor of Trust' through a Root CA - which can later be taken offline for security reasons - and then use the Root CA to authorize an Issuing CA. The issuing CA will then authorize a user certificate which can then be used to log into the remote server without a password.

** Please Note that I will be creating the Issuing CA as a 'Subordinate' CA. This is because we will effectively be able to extend this hierarchy to a 3 tier PKI structure **
** This is only in name and there is nothing else that needs to be done outside of the following steps to make an 'Issuing CA' a 'Subordinate' or vice versa **

The following will show the steps that need to taken in order for the chain of trust to work for each level of a 2 tier heirarchy.
That is: *Root CA -> Issuing CA ->> Authorized User Certificate(s)* 

#### Root CA
1. Generate Key Pair
2. Create a self-signed Root Certificate
3. Establish Root Private Key as the HostKey
4. Establish Root Certificate as the HostCertificate
5. Establish the Chain of Authority 
6. Verify the Trust
7. Sign Sub CA Certificate
8. Pass on chain of authority
9. Go Offline

#### Sub CA
1. Generate Key Pair
2. Get Root CA to create a signed Sub CA Certificate
3. Establish Sub CA Private Key as the HostKey
4. Establish Sub CA Certificate as the HostCertificate
5. Trust User Certificates Signed by Sub CA
6. Append the Chain of Authority
7. Verify the Trust
8. Sign User Certificate
9. Pass on the chain of authority

#### User Certificate
1. Generate Key Pair
2. Get Sub CA to create a signed User Certificate
3. Copy over the chain of authority
4. Create user connection config
5. Test the connection to the server
