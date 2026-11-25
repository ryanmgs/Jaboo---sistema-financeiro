
const menuToggle = document.getElementById("menuToggle");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("sidebarOverlay");


function abrirMenu() {
    sidebar.classList.add("open");
    overlay.classList.add("visible");
}

function fecharMenu() {
    sidebar.classList.remove("open");
    overlay.classList.remove("visible");
}


menuToggle.addEventListener("click", abrirMenu);

overlay.addEventListener("click", fecharMenu);
