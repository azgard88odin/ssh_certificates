# ssh_certificates
Implementation of SSH certificates like x509 hierarchy (multi-tier)

## Introduction
The following is to explain, in the simplest way possible, how to implement a multi-tier SSH certificate hierarchy.
This mini-project is what helped me to understand how certificates and a chain-of-trust works.
I hope that this helps others to not only understand PKI fundamentals a bit better, but also improve their overall security.
This project was completed using headless AntiX core Virtual Machines, but the principles will apply to all distributions. 

## Important Information
Ensure ssh is installed on your server.

### Common Commands (Syntax & Examples)
*SYNTAX:*
> ssh-keygen -t <ALGORITHM> -b <BYTE_SIZE> -f <FILENAME> -N <PASSWORD>
`ssh-keygen -s <PRIVATE_KEY> -I <CERTIFICATE_IDENTITY> -h -n <HOST_NAME> -V +<VALIDITY_PERIOD> <PUB_KEY_TO_SIGN>`
`ssh-keygen -s <PRIVATE_KEY> -I <CERTIFICATE_IDENTITY> -n <USER_NAME> -V <VALIDITY_PERIOD> <USER_PUB_KEY_TO_SIGN>`
*EXAMPLES:*
`# Create a 4096-bit RSA Key Pair with an Empty Password called 'ssh-root-ca'
# This creates: ssh-root-ca & ssh-root-ca.pub
ssh-keygen -t rsa -b 4096 -f ./ssh-root-ca -N ''
# Sign the ssh-sub-ca Host public key with the ssh-root-ca private key that is valid for 30 weeks
# The -I is the identity that will be shown on the ssh logs when used to sign in
# The -h is to signify a host certificate -n is the host's name`

In the **/etc/ssh/sshd_config** 
