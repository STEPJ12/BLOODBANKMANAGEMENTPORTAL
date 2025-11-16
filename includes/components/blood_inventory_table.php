<?php
// Blood Inventory Table Component
function renderBloodInventoryTable($preview = false) {
  // Sample data - in a real app, this would come from a database
  $bloodInventory = [
    ["type" => "A+", "redCross" => 45, "negroFirst" => 38, "status" => "High"],
    ["type" => "A-", "redCross" => 12, "negroFirst" => 10, "status" => "Low"],
    ["type" => "B+", "redCross" => 35, "negroFirst" => 28, "status" => "Medium"],
    ["type" => "B-", "redCross" => 8, "negroFirst" => 6, "status" => "Critical"],
    ["type" => "AB+", "redCross" => 15, "negroFirst" => 12, "status" => "Low"],
    ["type" => "AB-", "redCross" => 5, "negroFirst" => 4, "status" => "Critical"],
    ["type" => "O+", "redCross" => 65, "negroFirst" => 55, "status" => "High"],
    ["type" => "O-", "redCross" => 18, "negroFirst" => 15, "status" => "Low"],
  ];

  // For preview mode, only show first 4 rows
  $displayData = $preview ? array_slice($bloodInventory, 0, 4) : $bloodInventory;
  ?>
  <div class="border rounded-md">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Blood Type</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Red Cross</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Negros First</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($displayData as $item): ?>
          <tr>
            <td class="px-6 py-4 whitespace-nowrap font-medium"><?php echo $item["type"]; ?></td>
            <td class="px-6 py-4 whitespace-nowrap"><?php echo $item["redCross"]; ?> units</td>
            <td class="px-6 py-4 whitespace-nowrap"><?php echo $item["negroFirst"]; ?> units</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                <?php 
                if ($item["status"] === "High") {
                  echo "bg-green-100 text-green-800";
                } elseif ($item["status"] === "Medium") {
                  echo "bg-yellow-100 text-yellow-800";
                } elseif ($item["status"] === "Low") {
                  echo "bg-orange-100 text-orange-800";
                } else {
                  echo "bg-red-100 text-red-800";
                }
                ?>">
                <?php echo $item["status"]; ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
}
?>

