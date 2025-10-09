import "./bootstrap";
import Alpine from "alpinejs";
import intersect from "@alpinejs/intersect";
import persist from "@alpinejs/persist";

// Register Alpine plugins
Alpine.plugin(intersect);
Alpine.plugin(persist);

// Start Alpine
window.Alpine = Alpine;
Alpine.start();
