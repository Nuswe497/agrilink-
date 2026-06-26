 <section id="members" class="panel">
      <h2>Member Management</h2>
      <div class="card">
        <form class="form-grid">
          <input type="text" placeholder="Full Name" required>
          <input type="email" placeholder="Email Address" required>
          <select required>
            <option value="">Select Status</option>
            <option value="active">Active</option>
            <option value="pending">Pending Approval</option>
            <option value="suspended">Suspended</option>
          </select>
          <button type="submit" class="btn"><i class="fa-solid fa-user-plus"></i> Add Member</button>
        </form>

        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Nu Mwahie</td>
              <td>alinuswemwahimba5@gmail.com</td>
              <td><span style="color:#10b981; font-weight:600;">Active</span></td>
              <td><button class="action-btn">Edit</button></td>
            </tr>
            <!-- More rows... -->
          </tbody>
        </table>
      </div>
    </section>
