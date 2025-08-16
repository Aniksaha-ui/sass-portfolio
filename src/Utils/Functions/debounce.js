function debounce(func, delay) {
    console.log(func);
      func.timer = setTimeout(() => {
        func();
      }, delay);
  }

  
  export default debounce;