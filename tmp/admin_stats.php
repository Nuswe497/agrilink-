<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';

echo "Admin Dashboard Stats:\n";

// Members
$members = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'")->fetch_assoc()['count'];
echo "Members: $members\n";

// Contributions
$contribs = $conn->query("SELECT SUM(quantity) as total FROM contributions")->fetch_assoc()['total'];
echo "Honey: $contribs kg\n";

// Finances (from finance table)
$finances = $conn->query("SELECT SUM(amount) as total FROM finance")->fetch_assoc()['total'];
echo "Finance: MWK $finances\n";

// Hives
$hives = $conn->query("SELECT COUNT(*) as count FROM hives")->fetch_assoc()['count'];
echo "Hives: $hives\n";

// Inspections
$inspections = $conn->query("SELECT COUNT(*) as count FROM inspections")->fetch_assoc()['count'];
echo "Inspections: $inspections\n";

// Training Materials
$training = $conn->query("SELECT COUNT(*) as count FROM training_materials")->fetch_assoc()['count'];
echo "Training: $training\n";

// Stakeholders
$stakeholders = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'external'")->fetch_assoc()['count'];
echo "Stakeholders: $stakeholders\n";
?>
