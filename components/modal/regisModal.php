   <div class="modal fade" id="regisTeam" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="regisTeam" aria-hidden="true">
       <div class="modal-dialog modal-xl">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Post League</h5>
               </div>
               <div class="modal-body">
                   <form class="row g-3" id="registerTeam">
                       <div class="col-md-12">
                           <label for="team_image" class="form-label">Team Image:</label>
                           <input type="file" name="team_image" id="team_image" class="form-control" accept="image/*" required>
                       </div>
                       <div class="col-md-12">
                           <label for="team_name" class="form-label">Team Name<sup class="text-danger">*</sup>:</label>
                           <input name="team_name" class="form-control" id="team_name" type="text" required>
                       </div>
                       <div class="col-md-4">
                           <label for="team_repre" class="form-label">Team Representative<sup class="text-danger">*</sup>:</label>
                           <input name="team_repre" class="form-control" id="team_repre" type="text" required>
                       </div>
                       <div class="col-md-4">
                           <label for="email" class="form-label">Email<sup class="text-danger">*</sup>:</label>
                           <input name="email" class="form-control" id="email" type="text" required>
                       </div>
                       <div class="col-md-4">
                           <label for="phone" class="form-label">Phone number<sup class="text-danger">*</sup>:</label>
                           <input name="phone" class="form-control" id="phone" type="text" required>
                       </div>
                       <div class="col-md-12 mt-3">
                           <button type="button" class="btn btn-success btn-sm" onclick="addRow()">
                               + Add Player
                           </button>
                           <label class="form-label">Team Members (Minimum 5 Players)</label>

                           <table class="table table-bordered" id="playerTable">
                               <thead>
                                   <tr>
                                       <th>Name</th>
                                       <th>Age</th>
                                       <th>Role</th>
                                       <th>Jersey No.</th>
                                       <th>Gender</th>
                                       <th>Action</th>
                                   </tr>
                               </thead>

                               <tbody id="playerBody">
                                   <!-- rows will be generated -->
                               </tbody>
                           </table>


                       </div>



                       <div class="modal-footer">
                           <input type="hidden" name="id" value="<?php echo $id; ?>">
                           <input type="hidden" name="userId" value="<?php echo $userId; ?>">
                           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                           <button type="submit" class="btn btn-primary">
                               Register
                           </button>
                       </div>
                   </form>
               </div>

           </div>
       </div>
   </div>
   <script>
       let minRows = 5;

       // initialize 5 rows
       window.onload = function() {
           for (let i = 0; i < minRows; i++) {
               addRow();
           }
       };

       function addRow() {
           let tbody = document.getElementById("playerBody");

           let row = document.createElement("tr");

           row.innerHTML = `
        <td><input type="text" name="player_name[]" class="form-control" required></td>
        <td><input type="number" name="player_age[]" class="form-control" required></td>
        <td>
             <input type="text" name="player_role[]" class="form-control" required>

        </td>
        <td><input type="number" name="jersey_number[]" class="form-control" required></td>
        <td>
            <select name="gender[]" class="form-control" required>
                <option value="">Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                Delete
            </button>
        </td>
    `;

           tbody.appendChild(row);
       }

       function removeRow(btn) {
           let tbody = document.getElementById("playerBody");

           if (tbody.rows.length <= minRows) {
               alert("Minimum 5 players required!");
               return;
           }

           btn.closest("tr").remove();
       }
   </script>