// JavaScript to handle dynamic course update based on category selection

document.addEventListener("DOMContentLoaded", function () {
    // Get the course from the URL (if passed)
    const urlParams = new URLSearchParams(window.location.search);
    const selectedCourse = urlParams.get('course');

    // Preselect the course in the dropdown if it's passed in the URL
    if (selectedCourse) {
        const courseDropdown = document.getElementById('course');
        for (let i = 0; i < courseDropdown.options.length; i++) {
            if (courseDropdown.options[i].value === selectedCourse) {
                courseDropdown.selectedIndex = i;
                break;
            }
        }
    }
});
