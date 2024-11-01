=== VoxedIn ===
Contributors: Nickwiseoutlook
Tags: authentication,security,login,android,iphone,biometric,multi-factor
Requires at least: 3.5
Tested up to: 3.5
Stable tag: 0.95
License: Modified BSD

Secure Login using Voice Biometrics for your WordPress site.

== Description ==

This a plugin that integrates VoxedIn and WordPress. VoxedIn is a Smartphone 
app and web toolkit that lets your users log in to your site using voice biometrics.  
Once you have activated this plugin, your users can download the VoxedIn app 
and register their voice.  They will then be asked to authenticate their voice 
each time they log in.


== Installation ==
1. Install and activate the plugin.
2. Request access credentials via the VoxedIn website www.voxedin.com/get-access-credentials
3. As an administrator, go to the VoxedIn Settings page and enter the credentials 
   that we send to you.
4. Ensure you have the VoxedIn app on your smartphone.
   (The beta version can be downloaded from www.voxedin.com)
5. Log in as a normal user, edit your profile and enable VoxedIn.
6. Use the VoxedIn app to scan the QR code that will be displayed and
   record your voice as requested.
7. Log out of your WordPress account and then log in again by scanning the
   QR code with the VoxedIn app when it is displayed.
8. Enjoy the voice biometrics goodness.
                                      
== Frequently Asked Questions ==
                                      
= Is any personal information transferred to VoxedIn? =

No. We don't need to know anything about you or your users, all we do is check that
the voice registered against a unique ID is the same voice logging in. In this
plugin the unique ID is created by hashing the user's username together with
the access key that we provide to you. This ensures that VoxedIn cannot in
any way reverse engineer your usernames from the unique IDs it receives.

= How is the interaction between my site and VoxedIn secured? =

We communicate with your site via the users browser, which marshals the login
process between our two systems; there are no new routes needed in or out of
your servers.  Messages are encrypted using 256-bit AES encryption and a shared
key that we both hold secret.
                                                                         
When the user initiates a login, your site forwards an encrypted message to our
site via a form post.  We handle the voice biometric authentication and send the
results back via another encrypted form post to your site.
                                                                                                                                                
= The iPhone app hangs while uploading audio, what to do ? =

This is usually due to a network interruption. You can cancel and scan the 
code again, or wait.  We're working on making this smoother.
                                                                           
== Screenshots ==

1. Using VoxedIn to log in.
2. Enabling VoxedIn as a user.
3. Enabling VoxedIn as an admin.

== Changelog ==

= 0.95 =
* Updated backend server to new deployment

= 0.90 =
* First stable beta release
 
== License ==
                                                                           
Copyright (c) 2013, Nick Wise  (email : nick.wise@outlook.com)
All rights reserved.
                      
Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:
                                                                                
Redistributions of source code must retain the above copyright notice, this list
of conditions and the following disclaimer.
                                                                                
Redistributions in binary form must reproduce the above copyright notice, this
list of conditions and the following disclaimer in the documentation and/or  
other materials provided with the distribution.
                                                                             
Neither the names of VoxedIn nor the names of any contributors may
be used to endorse or promote products derived from this software without
specific prior written permission.
                                                                         
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE 
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.