import { Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { Html, OrbitControls, useTexture } from '@react-three/drei';
import { Link, useNavigate } from 'react-router-dom';
import * as THREE from 'three';
import { api } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';

function ProductPlane({ product, position, onClick, rotate }) {
  const [tex, setTex] = useState(null);
  useEffect(() => {
    const img = product.image || product.images?.[0];
    if (!img) return;
    new THREE.TextureLoader().load(img, (t) => {
      t.colorSpace = THREE.SRGBColorSpace;
      setTex(t);
    });
  }, [product]);
  const ref = useRef();
  useFrame((state) => {
    if (rotate && ref.current) {
      ref.current.rotation.y = Math.sin(state.clock.elapsedTime * 0.4) * 0.15;
    }
  });
  return (
    <group position={position} onClick={(e) => { e.stopPropagation(); onClick(product); }}>
      <mesh ref={ref} castShadow receiveShadow>
        <planeGeometry args={[0.9, 1.2]} />
        <meshStandardMaterial map={tex} transparent roughness={0.6} metalness={0.1} side={THREE.DoubleSide} />
      </mesh>
      <mesh position={[0, -0.7, 0.01]}>
        <planeGeometry args={[0.9, 0.18]} />
        <meshBasicMaterial color="#000000" transparent opacity={0.35} />
      </mesh>
      <Html position={[0, -0.7, 0.02]} center style={{ pointerEvents: 'none', color: '#fff', fontSize: '9px', textAlign: 'center', textShadow: '0 1px 4px #000', width: '90px' }}>
        <div style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{product.name}</div>
        <div style={{ color: '#60a5fa', fontWeight: 700 }}>{product.price} EGP</div>
      </Html>
    </group>
  );
}

function Shelf({ products, position, section, onSelect }) {
  const items = products.slice(0, 6);
  return (
    <group position={position}>
      <mesh position={[0, 0.1, -0.2]} receiveShadow>
        <boxGeometry args={[4.2, 0.08, 0.6]} />
        <meshStandardMaterial color="#1e293b" roughness={0.8} />
      </mesh>
      <mesh position={[0, 1.6, -0.25]}>
        <boxGeometry args={[4.4, 0.06, 0.02]} />
        <meshStandardMaterial color="#2563eb" emissive="#2563eb" emissiveIntensity={0.4} />
      </mesh>
      <Html position={[0, 1.8, -0.25]} center style={{ pointerEvents: 'none', color: '#fff', fontWeight: 800, letterSpacing: '4px', textTransform: 'uppercase', fontSize: '12px' }}>
        {section}
      </Html>
      {items.map((p, i) => (
        <ProductPlane key={p.id} product={p} position={[(i - (items.length-1)/2)*0.7, 0.8, -0.15]} onClick={onSelect} rotate />
      ))}
    </group>
  );
}

function StoreScene({ products, onSelect }) {
  const groups = useMemo(() => {
    const map = {};
    products.forEach(p => { const c = p.category || 'Other'; if(!map[c]) map[c]=[]; map[c].push(p); });
    return map;
  }, [products]);
  const sections = Object.keys(groups);
  return (
    <>
      <color attach="background" args={['#0b1026']} />
      <fog attach="fog" args={['#0b1026', 6, 16]} />
      <ambientLight intensity={0.8} />
      <directionalLight position={[6, 8, 6]} intensity={1.4} castShadow />
      <directionalLight position={[-6, 6, 4]} intensity={0.8} color="#93c5fd" />
      <hemisphereLight intensity={0.4} color="#bfdbfe" groundColor="#020617" />
      <mesh rotation={[-Math.PI/2, 0, 0]} position={[0, 0, 0]} receiveShadow>
        <planeGeometry args={[24, 36]} />
        <meshStandardMaterial color="#0f172a" roughness={0.95} />
      </mesh>
      <mesh position={[0, 3, -4]}>
        <planeGeometry args={[14, 6]} />
        <meshStandardMaterial color="#020617" roughness={0.9} />
      </mesh>
      {sections.map((sec, idx) => (
        <Shelf key={sec} section={sec} products={groups[sec]} position={[0, 0, -3 - idx*4]} onSelect={onSelect} />
      ))}
      <OrbitControls makeDefault enableDamping dampingFactor={0.08} minDistance={4} maxDistance={9} minPolarAngle={Math.PI/6} maxPolarAngle={Math.PI/2.1} target={[0, 1.2, -3]} />
    </>
  );
}

export default function VirtualStore() {
  const { addToCart, toast } = useStore();
  const navigate = useNavigate();
  const [products, setProducts] = useState([]);
  const [selected, setSelected] = useState(null);
  const [qty, setQty] = useState(1);
  const [model, setModel] = useState('');
  const [color, setColor] = useState('');
  useEffect(() => {
    api.get('/api/products.php?filter=all').then(r => setProducts(r.items || [])).catch(()=>{});
  }, []);
  const variants = selected?.variants || [];
  const colors = useMemo(() => {
    if (!variants.length) return [];
    const v = variants.find(x => x.model === model) || variants[0];
    return v.colors || [];
  }, [variants, model]);
  useEffect(() => {
    if (selected && variants.length) {
      setModel(variants[0].model);
      setColor(variants[0].colors?.[0]?.name || '');
    } else {
      setModel(''); setColor('');
    }
    setQty(1);
  }, [selected]);
  const stock = useMemo(() => {
    if (!selected) return 0;
    if (!variants.length) return selected.stockTotal || 0;
    const v = variants.find(x => x.model === model) || variants[0];
    const c = v.colors?.find(x => x.name === color) || v.colors?.[0];
    return c?.stock || 0;
  }, [selected, model, color, variants]);
  const add = () => {
    if (!selected) return;
    if (variants.length && (!model || !color)) { toast('اختر الموديل واللون', 'error'); return; }
    if (stock <= 0) { toast('نفذ المخزون', 'error'); return; }
    addToCart({ productId: selected.id, name: selected.name, image: selected.image || selected.images?.[0], price: selected.price, model: variants.length ? model : null, color: variants.length ? color : null, qty });
    toast('تمت الإضافة للسلة', 'success');
  };
  return (
    <div style={{ position: 'fixed', inset: 0, background: '#0b1026' }}>
      <div style={{ position: 'absolute', top: 16, left: 16, zIndex: 10, display: 'flex', gap: 10 }}>
        <Link to="/" className="btn btn-outline" style={{ background: 'rgba(255,255,255,0.9)' }}>← العودة للمتجر</Link>
        <Link to="/shop" className="btn btn-primary">كل المنتجات</Link>
      </div>
      <div style={{ position: 'absolute', top: 16, right: 16, zIndex: 10, color: '#fff', textAlign: 'right' }}>
        <div style={{ fontWeight: 800 }}>MOBI BOOST VIRTUAL STORE</div>
        <div style={{ fontSize: 12, opacity: 0.8 }}>اسحب/دور وانقر على المنتج لعرضه</div>
      </div>
      <div style={{ position: 'absolute', inset: 0 }}>
        <Canvas shadows camera={{ fov: 60 }}>
          <Suspense fallback={null}>
            <StoreScene products={products} onSelect={setSelected} />
          </Suspense>
        </Canvas>
      </div>
      {selected && (
        <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(1200px 800px at 50% 20%, rgba(37,99,235,0.25), transparent), rgba(2,6,23,0.85)', display: 'grid', placeItems: 'center', zIndex: 20 }}>
          <div style={{ width: 'min(920px, 94vw)', background: 'linear-gradient(180deg, rgba(30,41,59,0.95), rgba(15,23,42,0.98))', border: '1px solid rgba(59,130,246,0.5)', borderRadius: 20, overflow: 'hidden', boxShadow: '0 60px 120px -60px rgba(59,130,246,0.6)', display: 'grid', gridTemplateColumns: '1.1fr 1fr' }}>
            <div style={{ position: 'relative', height: 420 }}>
              <img src={selected.image || selected.images?.[0]} alt={selected.name} style={{ width: '100%', height: '100%', objectFit: 'contain', padding: 40 }} />
              <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(400px 200px at 50% 10%, rgba(96,165,250,0.25), transparent)' }} />
            </div>
            <div style={{ padding: 32, color: '#fff', display: 'flex', flexDirection: 'column', gap: 16 }}>
              <div>
                <div style={{ opacity: 0.7, letterSpacing: 4, textTransform: 'uppercase', fontSize: 11 }}>{selected.category}</div>
                <h2 style={{ margin: '8px 0 0', fontSize: 28 }}>{selected.name}</h2>
              </div>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 12 }}>
                <div style={{ fontSize: 32, fontWeight: 800 }}>{selected.price} EGP</div>
                {selected.compareAtPrice > selected.price && <div style={{ opacity: 0.5, textDecoration: 'line-through' }}>{selected.compareAtPrice} EGP</div>}
              </div>
              <p style={{ opacity: 0.8, margin: 0 }}>{selected.description}</p>
              {variants.length > 0 && (
                <>
                  <div>
                    <div style={{ marginBottom: 8, opacity: 0.8 }}>الموديل</div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                      {variants.map(v => <button key={v.model} onClick={() => { setModel(v.model); setColor(v.colors?.[0]?.name || ''); }} style={{ padding: '8px 14px', borderRadius: 999, border: model===v.model?'1px solid #60a5fa':'1px solid rgba(148,163,184,0.5)', background: model===v.model?'rgba(37,99,235,0.4)':'transparent', color: '#fff' }}>{v.model}</button>)}
                    </div>
                  </div>
                  {colors.length > 0 && (
                    <div>
                      <div style={{ marginBottom: 8, opacity: 0.8 }}>اللون</div>
                      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                        {colors.map(c => <button key={c.name} onClick={() => setColor(c.name)} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '6px 12px', borderRadius: 999, border: color===c.name?'1px solid #60a5fa':'1px solid rgba(148,163,184,0.5)', background: 'transparent', color: '#fff' }}><span style={{ width: 16, height: 16, borderRadius: '50%', background: c.hex, boxShadow: '0 0 0 1px rgba(255,255,255,0.4)' }} />{c.name}</button>)}
                      </div>
                    </div>
                  )}
                </>
              )}
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <div style={{ display: 'flex', alignItems: 'center', border: '1px solid rgba(148,163,184,0.5)', borderRadius: 999 }}>
                  <button onClick={() => setQty(q=>Math.max(1,q-1))} style={{ background: 'transparent', color: '#fff', border: 'none', width: 36, height: 36 }}>-</button>
                  <span style={{ minWidth: 30, textAlign: 'center' }}>{qty}</span>
                  <button onClick={() => setQty(q=>Math.min(stock||1,q+1))} style={{ background: 'transparent', color: '#fff', border: 'none', width: 36, height: 36 }}>+</button>
                </div>
                <div style={{ opacity: 0.7 }}>{stock > 0 ? `متوفر: ${stock}` : 'نفذ المخزون'}</div>
              </div>
              <div style={{ display: 'flex', gap: 12, marginTop: 'auto' }}>
                <button className="btn btn-primary btn-lg" onClick={add} disabled={stock <= 0}>أضف للسلة</button>
                <button className="btn btn-outline" onClick={() => { add(); navigate('/cart'); }} disabled={stock <= 0} style={{ background: 'transparent', color: '#fff', borderColor: 'rgba(148,163,184,0.6)' }}>اشتري الآن</button>
                <button className="btn btn-outline" onClick={() => setSelected(null)} style={{ background: 'transparent', color: '#fff', borderColor: 'rgba(148,163,184,0.6)' }}>إغلاق</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
