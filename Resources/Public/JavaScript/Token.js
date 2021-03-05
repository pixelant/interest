document.addEventListener('DOMContentLoaded', function(){
  let matchedElements = document.querySelectorAll('[data-event-name]');
  let responseLabel = document.createElement('p');
  let req = new XMLHttpRequest();

  matchedElements.forEach(function (elem) {
      if (elem.dataset.eventName === 'setup:token:clicked'){
          elem.addEventListener('click', function () {
            req.open("POST", window.location.host+'/rest/authentication');
            req.setRequestHeader("Authorization", "Basic dmxhZHlzbGF2LnZpbGRhbm92OlZ2VjEyMzQ1");
            req.send();
          })

        req.onload = function (){
          if (req.status !== 200){
            responseLabel.innerHTML = req.statusText;
            elem.closest('div').append(responseLabel);
          } else {
            let response = JSON.parse(req.response)[0];
            responseLabel.innerHTML = "Token: "+response.token;
            elem.closest('div').append(responseLabel);
          }
        }
      }
  })
});
