document.addEventListener('DOMContentLoaded', function() {
  const headerTitle = document.querySelector('.header-title');
  headerTitle.innerHTML = 'Roundcube Logs';
	headerTitle.classList.add('plugin-title');
  const table = document.getElementById('message_history_v2');
  const headers = table.getElementsByTagName('th');
  
  for (let i = 0; i < headers.length; i++) {
    headers[i].innerHTML += '<span class="arrow"></span>';
    headers[i].setAttribute('onclick', 'sortTable(' + i + ')');
  }

const searchBox = document.querySelector('#table_search');
  const rows = document.querySelectorAll('.message_history_table tbody tr');
  searchBox.addEventListener('input', function() {
    const query = searchBox.value.toLowerCase();
    rows.forEach(row => {
      const cells = row.querySelectorAll('td');
      let shouldHide = true;
      cells.forEach(cell => {
        if (cell.textContent.toLowerCase().indexOf(query) > -1) {
          shouldHide = false;
        }
      });
      if (shouldHide) {
        row.style.display = 'none';
      } else {
        row.style.display = '';
      }
    });
  });
	$('#message_history_v2').css('overflow-y', 'scroll');
});

function sortTable(n) {
  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  table = document.getElementById("message_history_v2");
  switching = true;
  // Set the sorting direction to ascending:
  dir = "asc";
  /* Make a loop that will continue until
  no switching has been done: */
  while (switching) {
    // Start by saying: no switching is done:
    switching = false;
    rows = table.rows;
    /* Loop through all table rows (except the
    first, which contains table headers): */
    for (i = 1; i < (rows.length - 1); i++) {
      // Start by saying there should be no switching:
      shouldSwitch = false;
      /* Get the two elements you want to compare,
      one from current row and one from the next: */
      x = rows[i].getElementsByTagName("td")[n];
      y = rows[i + 1].getElementsByTagName("td")[n];
      /* Check if the two rows should switch place,
      based on the direction, asc or desc: */
      if (dir == "asc") {
        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
          // If so, mark as a switch and break the loop:
          shouldSwitch= true;
          break;
        }
      } else if (dir == "desc") {
        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
          // If so, mark as a switch and break the loop:
          shouldSwitch= true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      /* If a switch has been marked, make the switch
      and mark that a switch has been done: */
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      // Each time a switch is done, increase this count by 1:
      switchcount ++;
    } else {
      /* If no switching has been done AND the direction is "asc",
      set the direction to "desc" and run the while loop again. */
      if (switchcount == 0 && dir == "asc") {
        dir = "desc";
        switching = true;
      }
    }
  }
  // Toggle the direction of the arrow:
  var arrow = table.getElementsByTagName('th')[n].querySelector('.arrow');
  arrow.classList.toggle('arrow-up');
  arrow.classList.toggle('arrow-down');
}
