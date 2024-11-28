# ssh_certificates
Implementation of SSH certificates like x509 hierarchy (multi-tier)

## Introduction
The following is to explain, in the simplest way possible, how to implement a multi-tier SSH certificate hierarchy.
This mini-project is what helped me to understand how certificates and a chain-of-trust works.
I hope that this helps others to not only understand PKI fundamentals a bit better, but also improve their overall security.
This project was completed using headless AntiX core Virtual Machines, but the principles will apply to all distributions. 


## Important Information
Ensure ssh is installed on your server.
Do NOT add public keys to the authorized_keys file (this will force Public Key Authentication instead of certificates)
Do NOT say 'Yes' to fingerprints unless transferring files (remove the fingerprints setup)
Once certificates are setup, deactivate password authentication in the sshd_config

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
```

### Important Configurations
In the **/etc/ssh/sshd_config**:
> HostCertificate
---
