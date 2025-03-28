# SSH Certificates - Setting Up a Multi-Tier Hierarchy
Implementation of SSH certificates like x509 hierarchy (multi-tier)

## Table of Contents
- [Introduction](#introduction)
- [Important Information](#important-information)
  - [File/Certificate/Key Transfer](#filecertificatekey-transfer)
  - [Common Commands](#common-commands-syntax--examples)
  - [Important Configurations](#important-configurations)
- [Overview](#overview)
- [Certificate Authorities](#certificate-authorities)
  - [Root CA](#root-ca)
  - [Sub CA](#sub-ca)
  - [User Certificates](#user-certificates)
- [Visual Guide to SSH Certificate Hierarchy](#visual-guide-to-ssh-certificate-hierarchy)
- [Troubleshooting](#troubleshooting)
- [Additional Resources](#additional-resources)

## Introduction
The following is to explain, in the simplest way possible, how to implement a multi-tier SSH certificate hierarchy.
This mini-project is what helped me to understand how certificates and a chain-of-trust works.
This also shows you the basic steps in setting this up manually, which then can assist in automating the process.
I hope that this helps others to not only understand PKI fundamentals a bit better, but also improve their overall security.
This project was completed using headless AntiX core Virtual Machines, but the principles will apply to all distributions. 


## Important Information
- Ensure ssh is installed on your server.
- Add the relevant hostnames to the /etc/hostname file on each VM - example: ssh-root-ca || ssh-sub-ca || ...
- Do NOT add public keys to the authorized_keys file (this will force Public Key Authentication instead of certificates)
- Do NOT say 'Yes' to fingerprints unless transferring files (remove the fingerprints setup)
- Once certificates are setup, deactivate password authentication in the sshd_config
- Ensure the chain of authority is copied over to the respective servers
- **Shred** any sensitive files, if applicable, after use
- Decide on the best way for you to transfer the files securely to each respective server

### File/Certificate/Key Transfer
During this exercise you will at times need to transfer files to and from certain servers. There are many ways to approach this problem. I will first share with you the easiest solution that avoids having to approve the server fingerprints during an ssh/scp connection
- Web Server (Private Network)<br/>
I would **NOT** advise this method to be used in a **real life situation**, but since the purpose of this exercise is to understand how to set up a multi-tier PKI with SSH Certificates, the easiest way is to have a Web Server like Apache2 in a separate VM and on each server which you purge after you are done transferring files.
```
//Install Apache2 on a Debian based server
sudo apt install apache2
systemctl start apache2

//Offer files on the server
cp /path/to/file /var/www/html/

//The best would be to download the certificates onto the CA server needed for the signing
curl -O 192.168.123.123/certificate-to-sign-cert.pub // Download the file onto the current server into the cwd (current working directory)
curl -F "file@=./file-to-send" 192.168.123.123/      // Send the file to the webserver

//If you are going to offer a private key for transfer make sure you set the permissions when offering the file and when downloading the file
chmod 644 private-key // When offering on the web server
chmod 600 private-key // When key is downloaded onto the server to be used

//IMPORTANT - The following is how to securely remove files from your server
//The following command 'shred' will overwrite the file(s) in question 20 times and then remove them
//This is so that no sensitive data can be extracted from the files removed
//FYI - The 'rm' command does not guarantee that the file removed, cannot be recovered through digital forensics
shred -n 20 -u file1 file2 file3 file4

//Once you are done with the Apache server on your CA servers, purge from the server
//The following command is on a Debian based server
apt purge apache2
```
- Web Server (Mutual TLS)<br/>
To practice with a more secure environment, you can setup a Mutual TLS relationship for your Apache2 server. I will probably write another guide on how to do this as this is out-of-scope for the current exercise. But essentially, this means that only the Hosts with an authorized certificate will be able to connect to your Web Server and download or upload the files in question.   
- SSH/SCP Transfer (Not Advised)<br/>
You can go the traditional route of quickly transferring files with SCP or copying the file contents to your clipboard and then SSHing into the other server and copying over the file contents. The only issue is that defeats the purpose of setting up certificates (avoiding the TOFU issue). This also means to verify that your certificate authentication is working, you will need to remove the fingerprints approved in the 'known_hosts' file.
- Shared Folder<br/>
Since this exercise uses VMs you can setup a shared network folder between all the VMs as a hub for all the files needed during the process.
- Physical Transfer<br/>
Copy the files on to a USB stick and attach it to each machine when required.

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
- *HostCertificate*<br/>
This option expects a path to the host machine's certificate. This is the certificate that will be presented to any client connecting to the server.
When connecting to a normal server for the first time, we are presented with a fingerprint. When a HostCertificate is set up, the fingerprint shown is the same Public Key seen when viewing the certificate with *ssh-keygen -Lf ssh-sub-ca-cert.pub*
- *HostKey*<br/>
This option expects a path to the host machine's private key. The private key here cannot, to my knowledge, have password protection.
This is the private key that will decrypt any data sent to it via the client's encrypted tunnel.
- *TrustedUserCAKeys*<br/>
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
- That is: *Root CA -> Issuing CA ->> Authorized User Certificate(s)* 

#### Root CA
1. Generate Key Pair
2. Create a self-signed Root Certificate
3. Establish Root Private Key as the HostKey
4. Establish Root Certificate as the HostCertificate
5. Establish the Chain of Authority
6. Edit /etc/hosts
7. Verify the Trust
8. Sign Sub CA Certificate
9. Pass on chain of authority
10. Go Offline

#### Sub CA
1. Generate Key Pair
2. Get Root CA to create a signed Sub CA Certificate
3. Establish Sub CA Private Key as the HostKey
4. Establish Sub CA Certificate as the HostCertificate
5. Trust User Certificates Signed by Sub CA
6. Append the Chain of Authority
7. Edit /etc/hosts
8. Verify the Trust
9. Sign User Certificate
10. Pass on the chain of authority

#### User Certificate
1. Generate Key Pair
2. Get Sub CA to create a signed User Certificate
3. Copy over the chain of authority
4. Create user connection config
5. Edit /etc/hosts
6. Test the connection to the server

--

### Certificate Authorities
#### Root CA
1. Generate 4096-bit RSA Key Pair as the root user. You can create these in the **/root/.ssh** directory. 
- Creates: root-ca-key(privatekey) & root-ca-key.pub(publickey)
```
ssh-keygen -t rsa -b 4096 -f ./root-ca-key -N ''
```
1. [x] Generate Key Pair

2. Create a self-signed Root Certificate valid for 1 year. Then copy over the key files and certificate to the /etc/ssh directory.
```
ssh-keygen -s root-ca-key -I ssh-root-ca -h -n ssh-root-ca.example.com -V +52w root-ca-key.pub
```
2. [x] Create Self-Signed Root Certificate

3-4. Change directory into the /etc/ssh directory, then execute the following.
```
echo "HostKey /etc/ssh/root-ca-key" >> ./sshd_config
echo "HostCertificate /etc/ssh/root-ca-key-cert.pub" >> ./sshd_config
```
3. [x] Establish Root Private Key as HostKey
4. [x] Establish Root Certificate as HostCertificate

5-6. The following will create the chain of authority in one command. Then add the edit needed to the /etc/hosts file
```
echo -n "@cert-authority ssh-root-ca.example.com " >> ./ssh_known_hosts && cat root-ca-key.pub >> ssh_known_hosts
echo "192.168.33.123  ssh-root-ca.example.com" >> /etc/hosts
```
5. [x] Establish Chain of Authority
6. [x] Edit /etc/hosts

7. Attempt a connection into the root server. If the trust is established, no fingerprint warning will appear and it will go straight to asking the password.
- Using the IP will not verify the host as the authority is setup for the hostname of the host server
```
ssh root@ssh-root-ca.example.com
```
7. [x] Verify the trust

8. Sign the Sub CA Certificate (after creation on the Sub CA Server). Before you commit to this step, you must first step up the Sub CA Virtual Machine, and as the root user, create the key pair and send over the public key to the Root CA for this step.
```
ssh-keygen -s root-ca-key -I ssh-sub-ca -h -n ssh-sub-ca.example.com -V +30w sub-ca-key.pub
```
8. [x] Sign the Sub CA Certificate

9. Pass on the chain of authority with the signed certificate.
- *Please replace 192.168.123.123 with the corresponding IP address of your server*
```
//Using secure copy to the Sub CA machine - You will have to clean out the fingerprint from the known_hosts file after the transfer
scp sub-ca-key-cert.pub ssh_known_hosts 192.168.123.123:/shared_folder/

//If you didn't scp the files from the Sub CA to the Root CA, you can download them with curl - as described earlier in this document
curl -O 192.168.123.123/sub-ca-key-cert.pub

//Send the files via curl - as described earlier in this document
curl -F "file=@./sub-ca-key-cert.pub" -F "file=@./ssh_known_hosts" 192.168.123.123
```
9. [x] Pass on the chain of authority

10. Shutdown the Root CA server.
```
shutdown now
```
10. [x] Go Offline

**Well Done!** You just successfully created a Root CA and established the Anchor of Trust.

#### Sub CA
1. Generate Sub CA Key Pair
```
ssh-keygen -t rsa -b 4096 -f ./sub-ca-key -N ''
```
1. [x] Generate Key Pair

2. Get Sub CA signed certificate by giving the
```
//Use whatever method described earlier in this text to get the signed certificate with the following command
ssh-keygen -s root-ca-key -I ssh-sub-ca -h -n ssh-sub-ca.example.com -V +30w sub-ca-key.pub

//REMEMBER, IF you have the private key on the Sub CA system, use the 'shred' command to securely remove the private key
shred -n 20 -u root-ca-key
```
2. [x] Get Root CA to create a signed Sub CA Certificate

3-5. Copy the key files and certificate to the /etc/ssh directory and add the following to the sshd_config file
```
echo "HostKey /etc/ssh/sub-ca-key" >> /etc/ssh/sshd_config
echo "HostCertificate /etc/ssh/sub-ca-key-cert.pub" >> /etc/ssh/sshd_config
echo "TrustedUserCAKeys /etc/ssh/sub-ca-key.pub" >> /etc/ssh/sshd_config
```
3. [x] Establish Sub CA Private Key as the HostKey
4. [x] Establish Sub CA Certificate as the HostCertificate
5. [x] Trust User Certificates Signed by Sub CA

6. In this step we must add both the public key of the Sub CA as a 'cert-authority' but also append the principal to the Root CA public key. For the Root CA public key, the principals should be: 'ssh-root-ca.example.com,ssh-sub-ca.example.com'. This is because the Root CA signed the Sub CA's certificate and this denotes its authority.
```
//After the appending of the root public key
//The following command adds the Sub CA public key to the ssh_known_hosts as a Certificate Authority
echo -n "@cert-authority ssh-sub-ca@example.com " >> ssh_known_hosts && cat sub-ca-key.pub >> ssh_known_hosts
```
6. [x] Append the Chain of Authority

7. To verify the in our current virtual environment, we must edit the /etc/hosts file to make sure we are connecting to a 'host' server and not just an IP address.
```
//Get the current machine's private IP address
hostname -I

//Replace 192.168.123.123 with the correct IP address of the Sub CA server.
echo "192.168.123.123  ssh-sub-ca.example.com" >> /etc/hosts
```
7. [x] Edit /etc/hosts

8. Connect to the Sub CA server to verify that the host is trusted
```
//This command at this point, should not request a fingerprint approval and go straight to requesting the password
//This means that the host is trusted
ssh root@ssh-sub-ca.example.com
```
8. [x] Verify the Trust

9. After obtaining the user public key, sign it with the Sub CA private key to create the User Certificate
```
//The -I is using an email address in this example, but you can use any identifier you wish
//The -h will not be used to create a user certificate as it is not a host
//The -n will denote the user that the certificate can login as
//Please note, the following authorizes a 'std_usr' account, this means that there will have to be a std_user account on the server 
ssh-keygen -s sub-ca-key -I user_email@example.com -n std_usr -V +10w user-key.pub

//Alternatively, you can authorize the certificate to login as root
ssh-keygen -s sub-ca-key -I user_email@example.com -n root -V +10w user-key.pub
```
9. [x] Sign User Certificate

10.
```
//Using secure copy to the User machine - You will have to clean out the fingerprint from the known_hosts file after the transfer
scp user-key-cert.pub ssh_known_hosts 192.168.123.123:/shared_folder/

//If you didn't scp the files from the Sub CA to the User machine, you can download them with curl - as described earlier in this document
curl -O 192.168.123.123/user-key-cert.pub

//Send the files via curl - as described earlier in this document
curl -F "file=@./sub-ca-key-cert.pub" -F "file=@./ssh_known_hosts" 192.168.123.123
```
10. [x] Pass on the chain of authority

#### User Certificates

**Well Done! You are almost there!**

1. Generate the user key pairs as in previous steps
```
ssh-keygen -t rsa -b 4096 -f ./user-key
```
1. [x] Generate Key Pair

2. Get the user public key signed
```
//Use whatever method described earlier in this text to get the signed certificate with the following command
//Please note, the following authorizes a 'std_usr' account, this means that there will have to be a std_user account on the server 
ssh-keygen -s sub-ca-key -I user_email@example.com -n std_usr -V +10w user-key.pub

//Alternatively, you can authorize the certificate to login as root
ssh-keygen -s sub-ca-key -I user_email@example.com -n root -V +10w user-key.pub

//REMEMBER, IF you have the private key on the User system, use the 'shred' command to securely remove the private key
shred -n 20 -u sub-ca-key
```
2. [x] Get Sub CA to create a signed User Certificate

3. Copy over the ssh_known_hosts file into the /etc/ssh/ directory on the user machine (must have sudo or root access)
```
//If the updated ssh_known_hosts is on your system
mv ssh_known_hosts /etc/ssh/

//If there is already information in the User machine's /etc/ssh/ssh_known_hosts file
cat ssh_known_hosts >> /etc/ssh/ssh_known_hosts
```
3. [x] Copy over the chain of authority

4. In the user (the one authorized in the certificate), add the following to the ./config file
```
//If you authorized std_user
Host myserveridentity
  Hostname ssh-sub-ca@example.com
  User std_user
  IdentityFile /home/std_user/.ssh/user-key
  CertificateFile /home/std_user/.ssh/user-key-cert.pub

//If you authorized root
Host myserveridentity
  Hostname ssh-sub-ca@example.com
  User root
  IdentityFile /root/.ssh/user-key
  CertificateFile /root/.ssh/user-key-cert.pub
```
4. [x] Create user connection config

5. As before, we must add the 'host' to the hosts file so that we don't connect to a plain IP address
```
echo "192.168.123.123 ssh-sub.ca.example.com" >> /etc/hosts
```
5. [x] Edit /etc/hosts

6. Test the connection with the following command. The connection should login immediately without any password or fingerprint requests
```
//SSH into the server with the identity denoted in the config file
ssh myserveridentity

//You should see one of the following prompts
std_user@ssh-sub-ca
root@ssh-sub-ca
```
6. [x] Test the connection to the server

## Visual Guide to SSH Certificate Hierarchy

```
┌─────────────────────┐
│                     │
│      Root CA        │ ← The ultimate source of trust, usually kept offline
│                     │   Creates self-signed certificate
└──────────┬──────────┘
           │ Signs
           ▼
┌─────────────────────┐
│                     │
│      Sub CA         │ ← The issuing authority that remains online
│                     │   Signs user certificates
└──────────┬──────────┘
           │ Signs
           ▼
┌─────────────────────┐
│                     │
│  User Certificate   │ ← Used by users to authenticate to servers
│                     │
└─────────────────────┘

Flow of Trust:
1. Root CA establishes the anchor of trust
2. Root CA signs Sub CA certificates
3. Sub CA signs user certificates
4. Servers trust Root CA and/or Sub CA
5. Users present their certificates to authenticate
```

### Key Files and Their Roles

| File Type | Purpose | Location | Example |
|-----------|---------|----------|---------|
| Private Key | Decrypts data, signs certificates | Secure server location | `root-ca-key`, `sub-ca-key` |
| Public Key | Encrypts data, verifies signatures | Shared openly | `root-ca-key.pub`, `sub-ca-key.pub` |
| Certificate | Signed public key with identity info | Used for authentication | `root-ca-key-cert.pub`, `user-key-cert.pub` |
| Known Hosts | Lists trusted certificate authorities | `/etc/ssh/ssh_known_hosts` | `@cert-authority hostname.example.com ssh-rsa...` |

## Troubleshooting

### Common Issues and Solutions

1. **Certificate not recognized**
   - Check that the hostname matches exactly what's in the certificate
   - Verify the certificate hasn't expired with `ssh-keygen -Lf cert.pub`
   - Ensure the CA's public key is properly listed in `ssh_known_hosts`

2. **Permission errors**
   - Private keys must have `chmod 600` permissions
   - Certificate files should have `chmod 644` permissions

3. **Still asking for password**
   - Check that `PasswordAuthentication no` is set in `/etc/ssh/sshd_config`
   - Verify that user is included in the certificate's principals

4. **Host verification failed**
   - Make sure the hostname matches what's in `/etc/hosts` and the certificate
   - Check that the CA's public key is present in `ssh_known_hosts`

## Additional Resources

- [OpenSSH Certificates Documentation](https://man.openbsd.org/ssh-keygen.1#CERTIFICATES)
- [SSH Academy: Certificate-based Authentication](https://www.ssh.com/academy/ssh/certificate-based-authentication)
- [Digital Ocean: How to Create an SSH CA](https://www.digitalocean.com/community/tutorials/how-to-create-an-ssh-ca-to-validate-hosts-and-clients-with-ubuntu)

Happy secure connecting!
