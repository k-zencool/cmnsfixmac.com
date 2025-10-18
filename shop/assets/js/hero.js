(function(){
  const root = document.querySelector('.hero-left');
  if (!root) return;

  const track = root.querySelector('.hero-track');
  const slides = [...root.querySelectorAll('.hero-slide')];
  const prev = root.querySelector('.hero-nav.prev');
  const next = root.querySelector('.hero-nav.next');
  const dotsWrap = root.querySelector('.hero-dots');

  let idx = 0, timer = null;

  // สร้าง dots
  slides.forEach((_, i) => {
    const b = document.createElement('button');
    b.setAttribute('aria-label','slide '+(i+1));
    b.addEventListener('click',()=>go(i,true));
    dotsWrap.appendChild(b);
  });

  function render(){
    track.style.transform = `translateX(-${idx*100}%)`;
    dotsWrap.querySelectorAll('button').forEach((d,i)=>d.classList.toggle('is-active',i===idx));
    slides.forEach((s,i)=>s.classList.toggle('is-active',i===idx));
  }

  function go(n,user=false){
    idx = (n+slides.length)%slides.length;
    render();
    if(user) reset();
  }

  function nextFn(){ go(idx+1); }
  function prevFn(){ go(idx-1,true); }

  function reset(){
    clearInterval(timer);
    timer = setInterval(nextFn,5000);
  }

  prev.addEventListener('click',prevFn);
  next.addEventListener('click',nextFn);
  root.addEventListener('mouseenter',()=>clearInterval(timer));
  root.addEventListener('mouseleave',reset);

  render(); reset();
})();
