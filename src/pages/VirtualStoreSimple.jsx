import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';

export default function VirtualStoreSimple() {
  const { addToCart, toast } = useStore();
  const [products, setProducts] = useState([]);
  const [selected, setSelected] = useState(null);
  const [qty, setQty] = useState(1);

  useEffect(() => {
    api.get('/api/products.php?filter=all').then(r => setProducts(r.items || [])).catch(()=>{});
  }, []);

  const add = () => {
    if (!selected) return;
    if ((selected.stockTotal||0) <= 0) { toast('نفذ المخزون', 'error'); return; }
    addToCart({ productId: selected.id, name: selected.name, image: selected.image || selected.images?.[0], price: selected.price, model: null, color: null, qty });
    toast('تمت الإضافة للسلة', 'success');
  };

  return (
    <div style={{ minHeight: '100vh', background: 'linear-gradient(180deg,#0b1026 0%,#020617 100%)', color: '#fff' }}>
      <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'16px 24px', borderBottom:'1px solid rgba(59,130,246,0.4)' }}>
        <div style={{ display:'flex', gap:12, alignItems:'center' }}>
          <Link to="/" className="btn btn-outline" style={{ background:'rgba(255,255,255,0.9)', color:'#0f172a' }}>← العودة للمتجر</Link>
          <div style={{ fontWeight:800 }}>MOBI BOOST - معرض افتراضي</div>
        </div>
        <Link to="/shop" className="btn btn-primary">كل المنتجات</Link>
      </div>
      <div style={{ padding:'24px', display:'grid', gridTemplateColumns:'repeat(auto-fill,minmax(220px,1fr))', gap:20 }}>
        {products.map(p => (
          <div key={p.id} onClick={() => setSelected(p)} style={{ cursor:'pointer', background:'linear-gradient(180deg,rgba(30,41,59,0.9),rgba(15,23,42,0.95))', border:'1px solid rgba(59,130,246,0.4)', borderRadius:16, overflow:'hidden', boxShadow:'0 40px 80px -40px rgba(59,130,246,0.4)' }}>
            <div style={{ height:200, background:'#020617' }}>
              <img src={p.image || p.images?.[0]} alt={p.name} style={{ width:'100%', height:'100%', objectFit:'contain', padding:16 }} />
            </div>
            <div style={{ padding:16 }}>
              <div style={{ fontSize:12, opacity:0.7 }}>{p.category}</div>
              <div style={{ fontWeight:700, marginTop:4 }}>{p.name}</div>
              <div style={{ color:'#60a5fa', fontWeight:800, marginTop:8 }}>{p.price} EGP</div>
            </div>
          </div>
        ))}
      </div>
      {selected && (
        <div style={{ position:'fixed', inset:0, background:'rgba(2,6,23,0.9)', display:'grid', placeItems:'center', zIndex:50 }}>
          <div style={{ width:'min(900px,94vw)', background:'linear-gradient(180deg,rgba(30,41,59,0.98),rgba(15,23,42,1))', border:'1px solid rgba(59,130,246,0.6)', borderRadius:20, overflow:'hidden', display:'grid', gridTemplateColumns:'1fr 1fr' }}>
            <div style={{ padding:40, background:'radial-gradient(400px 300px at 50% 30%,rgba(59,130,246,0.25),transparent)' }}>
              <img src={selected.image || selected.images?.[0]} alt={selected.name} style={{ width:'100%', height:'100%', objectFit:'contain' }} />
            </div>
            <div style={{ padding:32, display:'flex', flexDirection:'column', gap:16 }}>
              <div>
                <div style={{ opacity:0.7, letterSpacing:4, textTransform:'uppercase', fontSize:11 }}>{selected.category}</div>
                <h2 style={{ margin:'8px 0 0' }}>{selected.name}</h2>
              </div>
              <div style={{ fontSize:36, fontWeight:800, color:'#60a5fa' }}>{selected.price} EGP</div>
              <p style={{ opacity:0.8 }}>{selected.description}</p>
              <div style={{ display:'flex', alignItems:'center', gap:12 }}>
                <div style={{ display:'flex', alignItems:'center', border:'1px solid rgba(148,163,184,0.5)', borderRadius:999 }}>
                  <button onClick={()=>setQty(q=>Math.max(1,q-1))} style={{ background:'transparent', color:'#fff', border:'none', width:40, height:40 }}>-</button>
                  <span style={{ minWidth:40, textAlign:'center' }}>{qty}</span>
                  <button onClick={()=>setQty(q=>q+1)} style={{ background:'transparent', color:'#fff', border:'none', width:40, height:40 }}>+</button>
                </div>
                <div style={{ opacity:0.7 }}>{selected.stockTotal>0?`متوفر`:'نفذ المخزون'}</div>
              </div>
              <div style={{ display:'flex', gap:12, marginTop:'auto' }}>
                <button className="btn btn-primary btn-lg" onClick={add}>أضف للسلة</button>
                <button className="btn btn-outline" onClick={()=>setSelected(null)} style={{ background:'transparent', color:'#fff', borderColor:'rgba(148,163,184,0.6)' }}>إغلاق</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
