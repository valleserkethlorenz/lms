   <div class="modal fade" id="addCat" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addCat" aria-hidden="true">
       <div class="modal-dialog modal-xl">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Post League</h5>
               </div>
               <div class="modal-body">
                   <form class="row g-3" id="regisLeague">
                       <div class="col-md-12">
                           <label for="league_image" class="form-label">League Image:</label>
                           <input type="file" name="league_image" id="league_image" class="form-control" accept="image/*" required>
                       </div>
                       <div class="col-md-12">
                           <label for="league_name" class="form-label">League Name<sup class="text-danger">*</sup>:</label>
                           <input name="league_name" class="form-control" id="league_name" type="text" required>
                       </div>
                       <div class="col-md-6">
                           <label for="sport" class="form-label">Sport<sup class="text-danger">*</sup>:</label>
                           <select name="sport" id="" class="form-select">
                               <option selected disabled>Select Sport</option>
                               <option value="Basketball">Basketball</option>
                               <option value="Volleyball">Volleyball</option>
                           </select>
                       </div>
                       <div class="col-md-6">
                           <label for="division" class="form-label">Division<sup class="text-danger">*</sup>:</label>
                           <select name="division" id="division" class="form-select">
                               <option selected disabled>Select Division</option>
                               <option value="Under - 13">Under - 13</option>
                               <option value="Under - 18">Under - 18</option>
                               <option value="Under - 21">Under - 21</option>
                           </select>
                       </div>
                       <div class="col-md-6">
                           <label for="category" class="form-label">category<sup class="text-danger">*</sup>:</label>
                           <select name="category" id="category" class="form-select">
                               <option selected disabled>Select category</option>
                               <option value="Category 1">Category 1</option>
                               <option value="Category 2">Category 2</option>
                               <option value="Category 3">Category 3</option>

                           </select>
                       </div>
                       <div class="col-md-6">
                           <label for="format" class="form-label">format<sup class="text-danger">*</sup>:</label>
                           <select name="format" id="format" class="form-select">
                               <option selected disabled>Select format</option>
                               <option value="Single Elimination">Single Elimination</option>
                               <option value="Double Elimination">Double Elimination</option>
                               <option value="Round Robin">Round Robin</option>
                               <option value="Swiss System">Swiss System</option>
                               <option value="League / Season Format">League / Season Format</option>
                               <option value="Group Stage + Knockout">Group Stage + Knockout</option>
                               <option value="Knockout (General)">Knockout (General)</option>
                               <option value="Ladder Format">Ladder Format</option>
                               <option value="Friendly / Exhibition">Friendly / Exhibition</option>
                           </select>
                       </div>
                       <div class="col-md-3">
                           <label for="start_date" class="form-label">Start Date<sup class="text-danger">*</sup>:</label>
                           <input name="start_date" class="form-control" id="start_date" type="date" required>
                       </div>
                       <div class="col-md-3">
                           <label for="end_date" class="form-label">End Date<sup class="text-danger">*</sup>:</label>
                           <input name="end_date" class="form-control" id="end_date" type="date" required>
                       </div>
                       <div class="col-md-3">
                           <label for="regis_start" class="form-label">Registration Start<sup class="text-danger">*</sup>:</label>
                           <input name="regis_start" class="form-control" id="regis_start" type="date" required>
                       </div>
                       <div class="col-md-3">
                           <label for="regis_end" class="form-label">Registration End<sup class="text-danger">*</sup>:</label>
                           <input name="regis_end" class="form-control" id="regis_end" type="date" required>
                       </div>
                       <div class="col-md-6">
                           <label for="venue" class="form-label">venue<sup class="text-danger">*</sup>:</label>
                           <input name="venue" class="form-control" id="venue" type="text" required>
                       </div>
                       <div class="col-md-2">
                           <label for="max_team" class="form-label">max team<sup class="text-danger">*</sup>:</label>
                           <input name="max_team" class="form-control" id="max_team" type="text" required>
                       </div>
                       <div class="col-md-2">
                           <label for="max_player" class="form-label">max player<sup class="text-danger">*</sup>:</label>
                           <input name="max_player" class="form-control" id="max_player" type="text" required>
                       </div>
                       <div class="col-md-2">
                           <label for="reg_fee" class="form-label">Fee (optional):</label>
                           <input name="reg_fee" class="form-control" id="reg_fee" type="text">
                       </div>
                       <div class="col-md-12">
                           <label for="descrip" class="form-label">League Details<sup class="text-danger">*</sup>:</label>
                           <textarea name="descrip" id="descrip" class="form-control"></textarea>
                       </div>
                       <div class="col-md-12">
                           <label for="add_info" class="form-label">Eligibility Details<sup class="text-danger">*</sup>:</label>
                           <textarea name="add_info" id="add_info" class="form-control"></textarea>
                       </div>
                       <div class="col-md-12">
                           <label for="upload" class="form-label">upload guideline:</label>
                           <input type="file" name="upload" id="upload" class="form-control" accept="image/*">
                       </div>


                       <div class="modal-footer">
                           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                           <button type="submit" class="btn btn-primary">
                               Create
                           </button>
                       </div>
                   </form>
               </div>

           </div>
       </div>
   </div>
