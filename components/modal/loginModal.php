   <div class="modal fade" id="signUp" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="signUp" aria-hidden="true">
       <div class="modal-dialog modal-xl">
           <div class="modal-content">
               <div class="modal-header" style="color:white;">
                   <h5 class="modal-title">SIGN-UP</h5>
               </div>
               <div class="modal-body" style="font-family: head;">
                   <form class=" row g-2 needs-validation" id="signUp" novalidate>
                       <div class="col-md-4">
                           <label for="validationServer02" class="form-label text-black">First name<sup class="text-danger">*</sup>:</label>
                           <input type="text" class="form-control" id="validationServer02" name="fname" required placeholder="Enter the First Name" maxlength="16" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');">
                           <div class="invalid-feedback">
                               Please enter first name!
                           </div>
                       </div>

                       <div class="col-md-4">
                           <label for="validationServer02" class="form-label text-black">Last name<sup class="text-danger">*</sup>:</label>
                           <input type="text" class="form-control" id="validationServer03" name="lname" required placeholder="Enter the Last Name" maxlength="16" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');">
                           <div class="invalid-feedback">
                               Please enter middle name!
                           </div>
                       </div>
                       <div class="col-md-3">
                           <label for="mname" class="form-label text-black text-capitalize">middle name:</label>
                           <input name="mname" class="form-control" id="mname" type="text" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');" placeholder="Enter Middle Name">
                       </div>

                       <div class="col-md-1">
                           <label for="fname" class="form-label text-black text-capitalize">suffix:</label>
                           <select class="form-select" name="suffix">
                               <option selected disabled>Select Suffix</option>
                               <option value="Jr.">Jr.</option>
                               <option value="Sr.">Sr.</option>
                               <option value="I">I</option>
                               <option value="II">II</option>
                               <option value="III">III</option>
                               <option value="IV">IV</option>
                           </select>
                       </div>

                       <div class="col-md-6">
                           <label for="validationServer02" class="form-label text-black">Phone<sup class="text-danger">*</sup>:</label>
                           <input type="text" class="form-control" id="testingss" name="phone" required placeholder="09*********" maxlength="11" pattern="\d{11}" oninput="validateInput11()">
                           <div class="invalid-feedback">
                               Please enter phone!
                           </div>
                       </div>

                       <div class="col-md-6">
                           <label for="validationServer02" class="form-label text-black">Email<sup class="text-danger">*</sup>:</label>
                           <input type="text" class="form-control" id="validationServer04" name="email" required placeholder="example@gmail.com" pattern="^[a-zA-Z0-9]+@gmail\.com$" title="Please enter a valid email address ending with @gmail.com">


                           <div class="invalid-feedback">
                               Please enter email address!
                           </div>
                       </div>

                       <hr>
                       <label for="sub2" style="color:#01421c;">
                           <h4>Sercurity Information</h4>
                       </label>
                       <div class="col-md-12">
                           <label for="validationServer02" class="form-label text-black">Password<sup class="text-danger">*</sup>:</label>
                           <input type="password" class="form-control" id="validationServers01" name="pass1" required placeholder="Enter the account password " minlength="8" maxlength="21" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                           <span class="txt1" style="color:#01421c;">Show password</span>
                           <input type="checkbox" id="pass1" onclick="togglePasswordVisibility1()">
                           <div class="invalid-feedback">


                               Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters

                           </div>
                       </div>
                       <div class="col-md-12">
                           <label for="validationServer02" class="form-label text-black">Reenter-Password<sup class="text-danger">*</sup>:</label>
                           <input type="password" class="form-control" id="validationServers02" name="pass2" required placeholder="Enter the Unique Password" minlength="8" maxlength="21" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                           <span class="txt1" style="color:#01421c;">Show password</span>
                           <input type="checkbox" id="pass2" onclick="togglePasswordVisibility2()">
                           <div class="invalid-feedback">

                               Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters
                           </div>

                       </div>
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-target="#loginform" data-bs-toggle="modal">Close</button>
                   <button type="submit" class="btn " style="background-color:#01421c; color:white;" name="createUser">Register</button>
               </div>
               </form>

           </div>
       </div>
   </div>
   <div class="modal fade" id="loginModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="loginModal" aria-hidden="true">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header" style="color:white;">
                   <h5 class="modal-title">SIGN-UP</h5>
               </div>
               <div class="modal-body" style="font-family: head;">
                   <form class=" row g-2 needs-validation" id="loginModal" novalidate>
                       <div class="mb-3">
                           <label for="id" class="form-label">Email Address:</label>
                           <input type="text" class="form-control" id="email" name="email" required>
                       </div>
                       <div class="mb-3 text-left">
                           <label for="password" class="form-label">Password:</label>
                           <input type="password" class="form-control" id="password" name="password" required>
                           <span class="txt1" style="color:black; font-size:15px;">Show Password</span>
                           <input type="checkbox" id="showpass" onclick="viewPassword()">
                       </div>
                       <div class="modal-footer">

                           <button type="button" class="btn btn-secondary" data-bs-target="#loginform" data-bs-toggle="modal">Close</button>
                           <button type="submit" class="btn " style="background-color:#01421c; color:white;">Login</button>
                       </div>
                   </form>
               </div>
           </div>
       </div>
   </div>