<?php
// Patient Request Form Component
function renderPatientRequestForm($preview = false) {
  if ($preview) {
    ?>
    <div class="space-y-2">
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label for="bloodType" class="block text-xs font-medium text-gray-700">Blood Type</label>
          <select disabled class="mt-1 block w-full pl-3 pr-10 py-1 text-xs border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 rounded-md bg-gray-100">
            <option>Select</option>
          </select>
        </div>
        <div>
          <label for="units" class="block text-xs font-medium text-gray-700">Units Needed</label>
          <input type="number" id="units" disabled class="mt-1 block w-full px-3 py-1 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 bg-gray-100" placeholder="Enter units">
        </div>
      </div>
      <button disabled class="w-full py-1 px-4 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-red-700 opacity-50">
        Request Blood
      </button>
    </div>
    <?php
    return;
  }
  ?>
  <form class="space-y-6" method="post" action="process_blood_request.php">
    <div class="space-y-4">
      <h3 class="text-lg font-medium">Patient Information</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="patientName" class="block text-sm font-medium text-gray-700">Patient Name</label>
          <input type="text" id="patientName" name="patientName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter patient name" required>
        </div>

        <div class="space-y-2">
          <label for="patientId" class="block text-sm font-medium text-gray-700">Patient ID (if available)</label>
          <input type="text" id="patientId" name="patientId" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter patient ID">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="contactPerson" class="block text-sm font-medium text-gray-700">Contact Person</label>
          <input type="text" id="contactPerson" name="contactPerson" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter contact person name" required>
        </div>

        <div class="space-y-2">
          <label for="contactPhone" class="block text-sm font-medium text-gray-700">Contact Phone</label>
          <input type="tel" id="contactPhone" name="contactPhone" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter contact phone number" required>
        </div>
      </div>

      <div class="space-y-2">
        <label for="hospitalName" class="block text-sm font-medium text-gray-700">Hospital/Medical Facility</label>
        <input type="text" id="hospitalName" name="hospitalName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter hospital or medical facility name" required>
      </div>
    </div>

    <div class="space-y-4">
      <h3 class="text-lg font-medium">Blood Request Details</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="bloodType" class="block text-sm font-medium text-gray-700">Blood Type Needed</label>
          <select id="bloodType" name="bloodType" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md" required>
            <option value="" selected disabled>Select blood type</option>
            <option value="a_pos">A+</option>
            <option value="a_neg">A-</option>
            <option value="b_pos">B+</option>
            <option value="b_neg">B-</option>
            <option value="ab_pos">AB+</option>
            <option value="ab_neg">AB-</option>
            <option value="o_pos">O+</option>
            <option value="o_neg">O-</option>
          </select>
        </div>

        <div class="space-y-2">
          <label for="unitsNeeded" class="block text-sm font-medium text-gray-700">Units Needed</label>
          <input type="number" id="unitsNeeded" name="unitsNeeded" min="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter number of units" required>
        </div>
      </div>

      <fieldset class="space-y-2">
        <legend class="block text-sm font-medium text-gray-700">Urgency Level</legend>
        <div class="flex flex-wrap gap-4 mt-1">
          <div class="flex items-center">
            <input id="critical" name="urgency" type="radio" value="critical" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300">
            <label for="critical" class="ml-2 block text-sm text-red-600 font-medium">
              Critical (Immediate)
            </label>
          </div>
          <div class="flex items-center">
            <input id="urgent" name="urgency" type="radio" value="urgent" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300">
            <label for="urgent" class="ml-2 block text-sm text-orange-600 font-medium">
              Urgent (24 hours)
            </label>
          </div>
          <div class="flex items-center">
            <input id="normal" name="urgency" type="radio" value="normal" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300" checked>
            <label for="normal" class="ml-2 block text-sm text-gray-700">
              Normal (2-3 days)
            </label>
          </div>
          <div class="flex items-center">
            <input id="scheduled" name="urgency" type="radio" value="scheduled" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300">
            <label for="scheduled" class="ml-2 block text-sm text-gray-700">
              Scheduled Procedure
            </label>
          </div>
        </div>
      </fieldset>
      <div>

      <div class="space-y-2">
        <label for="preferredOrg" class="block text-sm font-medium text-gray-700">Preferred Organization</label>
        <select id="preferredOrg" name="preferredOrg" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md" required>
          <option value="" selected disabled>Select organization</option>
          <option value="red_cross">Red Cross</option>
          <option value="negros_first">Negros First</option>
          <option value="any">Any Available</option>
        </select>
      </div>

      <div class="space-y-2">
        <label for="medicalReason" class="block text-sm font-medium text-gray-700">Medical Reason for Request</label>
        <textarea id="medicalReason" name="medicalReason" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Briefly describe the medical reason for the blood request" required></textarea>
      </div>
    </div>

    <div class="space-y-4">
      <h3 class="text-lg font-medium">Barangay Referral</h3>

      <div class="space-y-2">
        <label for="barangay" class="block text-sm font-medium text-gray-700">Barangay</label>
        <select id="barangay" name="barangay" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md" required>
          <option value="" selected disabled>Select barangay</option>
          <option value="barangay1">Barangay 1</option>
          <option value="barangay2">Barangay 2</option>
          <option value="barangay3">Barangay 3</option>
          <option value="barangay4">Barangay 4</option>
          <option value="barangay5">Barangay 5</option>
        </select>
      </div>
    </div>

    <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
      Submit Blood Request
    </button>
  </form>
  <?php
}
?>

