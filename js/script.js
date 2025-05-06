//script.js
document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const menuItems = document.querySelectorAll('.menu-item');

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove 'active' class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            const category = button.getAttribute('data-category');
            console.log("Button clicked:", category); // ✅ DEBUG

            menuItems.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                console.log("Item category:", itemCategory); // ✅ DEBUG

                if (category.toLowerCase() === 'all' || category.toLowerCase() === itemCategory.toLowerCase()) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // Trigger 'All' filter on page load
    document.querySelector('[data-category="all"]')?.click();

    // Add confirmation for logout link
    const logoutLink = document.querySelector('.logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', (e) => {
            if (!confirm('Are you sure you want to log out?')) {
                e.preventDefault();
            }
        });
    }
});