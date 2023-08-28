document.addEventListener("DOMContentLoaded", function() {
  var sendButton = document.getElementById("rcmbtn112");
  sendButton.removeAttribute("onclick");
  
  sendButton.addEventListener("click", function(event) {
    var selectedOption = document.getElementById('compose-exercise').value;
    if (selectedOption === 'none') {
      showCustomPopup('Please select an exercise option from the dropdown.');
      //return; // Stop the execution of the send function
      event.preventDefault();
    } else {
	    removeCustomPopup();
	    rcmail.command('send', '', this, event);
    }
  });
});

var overlay;
var popup;

function showCustomPopup() {

  if (overlay && popup) {
    document.body.removeChild(overlay);
    document.body.removeChild(popup);
  }

  overlay = document.createElement('div');
  overlay.classList.add('custom-popup-overlay');

  popup = document.createElement('div');
  popup.setAttribute('id', 'custom-popup');
  popup.classList.add('custom-popup');

  var title = document.createElement('h4');
  title.textContent = 'No Exercise';

  var line = document.createElement('hr');
  
  var message = document.createElement('p');
  message.textContent = 'The "Exercise" field is empty. Would you like to enter one now?';
  message.classList.add('custom-popup-message');

  var dropdown = document.getElementById('compose-exercise').cloneNode(true);
  dropdown.removeAttribute('id');
  dropdown.classList.add('custom-popup-dropdown');

  var buttonContainer = document.createElement('div');
  buttonContainer.classList.add('custom-popup-button-container');

  var sendButton = document.createElement('button');
  sendButton.textContent = 'Send Message';
  sendButton.classList.add('custom-send-button', 'mainaction', 'send', 'btn', 'btn-primary');
  sendButton.addEventListener('click', function() {
    var selectedOption = dropdown.value;
    if (selectedOption !== 'none') {
      document.getElementById('compose-exercise').value = selectedOption;
      removeCustomPopup();
      // Send the message here
      rcmail.command('send', '', this, event);
    }
  });

  var cancelButton = document.createElement('button');
  cancelButton.textContent = 'Cancel';
  cancelButton.classList.add('custom-cancel-button', 'cancel', 'btn', 'btn-secondary');
  cancelButton.addEventListener('click', function() {
    removeCustomPopup();
  });

  buttonContainer.appendChild(sendButton);
  buttonContainer.appendChild(cancelButton);
  
  popup.appendChild(title);
  popup.appendChild(line);
  popup.appendChild(message);
  popup.appendChild(dropdown);
  popup.appendChild(buttonContainer);

  document.body.appendChild(overlay);
  document.body.appendChild(popup);
}

function removeCustomPopup() {
  if (overlay && popup) {
    document.body.removeChild(overlay);
    document.body.removeChild(popup);
    overlay = null; // Reset the overlay variable
    popup = null; // Reset the popup variable
  }
}


document.addEventListener("DOMContentLoaded", function() {
  var sendButton = document.getElementById("rcmbtn112");
  sendButton.removeAttribute("onclick");

  sendButton.addEventListener("click", function(event) {
    var selectedOption = document.getElementById('compose-exercise').value;
    if (selectedOption === 'none') {
      showCustomPopup();
      event.preventDefault(); // Prevent the default form submission
    }
  });
});
