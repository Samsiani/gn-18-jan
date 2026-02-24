/* ============================================
   GN Invoice SaaS - Demo Data
   Vue 3 Migration
   ============================================ */

export const COMPANY = {
  name: 'GN Industrial',
  nameKa: 'GN ინდასტრიალ',
  taxId: '404476218',
  address: 'თბილისი, ვაჟა-ფშაველას გამზ. 71',
  phone: '+995 599 123 456',
  email: 'info@gn-industrial.ge',
  website: 'www.gn-industrial.ge',
  bankName1: 'საქართველოს ბანკი',
  iban1: 'GE29BG0000000541851100',
  bankName2: 'თიბისი ბანკი',
  iban2: 'GE10TB7774936615100003',
  directorName: 'გიორგი ნოზაძე',
  reservationDays: 14,
  startingInvoiceNumber: 1001,
  invoicePrefix: 'GN',
  loginFooterNote: 'GN Industrial · Invoice System v2.0'
};

export const USERS = [
  { id: 1, name: 'გიორგი ნოზაძე', nameEn: 'Giorgi Nozadze', avatar: 'GN', role: 'admin', phone: '+995 599 100 001', email: 'g.nozadze@gn-industrial.ge', invoiceCount: 145, revenue: 892500 },
  { id: 2, name: 'ანა კვარაცხელია', nameEn: 'Ana Kvaratskhelia', avatar: 'AK', role: 'manager', phone: '+995 577 100 002', email: 'a.kvaratskhelia@gn-industrial.ge', invoiceCount: 98, revenue: 567800 },
  { id: 3, name: 'ლაშა ბერიძე', nameEn: 'Lasha Beridze', avatar: 'LB', role: 'sales', phone: '+995 555 100 003', email: 'l.beridze@gn-industrial.ge', invoiceCount: 72, revenue: 345200 },
  { id: 4, name: 'ნინო მეგრელაძე', nameEn: 'Nino Megreladze', avatar: 'NM', role: 'sales', phone: '+995 571 100 004', email: 'n.megreladze@gn-industrial.ge', invoiceCount: 56, revenue: 234100 },
  { id: 5, name: 'დავით ჭავჭავაძე', nameEn: 'Davit Chavchavadze', avatar: 'DC', role: 'accountant', phone: '+995 599 100 005', email: 'd.chavchavadze@gn-industrial.ge', invoiceCount: 0, revenue: 0 }
];

export const PRODUCTS = [
  { id: 1, sku: 'GN-PIPE-001', name: 'Steel Pipe DN100', nameKa: 'ფოლადის მილი DN100', brand: 'GN Steel', description: 'Hot-rolled seamless steel pipe', price: 85.50, stock: 250, reserved: 12, category: 'pipes' },
  { id: 2, sku: 'GN-PIPE-002', name: 'Steel Pipe DN150', nameKa: 'ფოლადის მილი DN150', brand: 'GN Steel', description: 'Hot-rolled seamless steel pipe', price: 125.00, stock: 180, reserved: 8, category: 'pipes' },
  { id: 3, sku: 'GN-VALVE-001', name: 'Gate Valve DN50', nameKa: 'შიბერი DN50', brand: 'ValveTech', description: 'Cast iron gate valve', price: 230.00, stock: 45, reserved: 3, category: 'valves' },
  { id: 4, sku: 'GN-VALVE-002', name: 'Ball Valve DN25', nameKa: 'ბურთულიანი სარქველი DN25', brand: 'ValveTech', description: 'Stainless steel ball valve', price: 78.50, stock: 120, reserved: 5, category: 'valves' },
  { id: 5, sku: 'GN-FIT-001', name: 'Elbow 90° DN50', nameKa: 'მუხლი 90° DN50', brand: 'GN Steel', description: 'Welded steel elbow fitting', price: 32.00, stock: 300, reserved: 15, category: 'fittings' },
  { id: 6, sku: 'GN-FIT-002', name: 'Tee DN80', nameKa: 'სამთავიანი DN80', brand: 'GN Steel', description: 'Equal tee pipe fitting', price: 55.00, stock: 200, reserved: 10, category: 'fittings' },
  { id: 7, sku: 'GN-FLG-001', name: 'Flange DN100 PN16', nameKa: 'ფლანცი DN100 PN16', brand: 'FlangeMax', description: 'Slip-on flange', price: 45.00, stock: 150, reserved: 7, category: 'flanges' },
  { id: 8, sku: 'GN-PMP-001', name: 'Centrifugal Pump 5.5kW', nameKa: 'ცენტრიდანული ტუმბო 5.5kW', brand: 'HydroFlow', description: 'Industrial centrifugal pump', price: 2850.00, stock: 8, reserved: 2, category: 'pumps' },
  { id: 9, sku: 'GN-PMP-002', name: 'Submersible Pump 3kW', nameKa: 'ჩაძირული ტუმბო 3kW', brand: 'HydroFlow', description: 'Deep well submersible pump', price: 1950.00, stock: 12, reserved: 1, category: 'pumps' },
  { id: 10, sku: 'GN-MET-001', name: 'Steel Sheet 2mm', nameKa: 'ფოლადის ფურცელი 2მმ', brand: 'MetalPro', description: '1250x2500mm cold-rolled', price: 185.00, stock: 90, reserved: 5, category: 'sheets' },
  { id: 11, sku: 'GN-MET-002', name: 'Steel Sheet 4mm', nameKa: 'ფოლადის ფურცელი 4მმ', brand: 'MetalPro', description: '1250x2500mm hot-rolled', price: 320.00, stock: 65, reserved: 3, category: 'sheets' },
  { id: 12, sku: 'GN-BLT-001', name: 'Hex Bolt M16x60', nameKa: 'ექვსწახნაგა ჭანჭიკი M16x60', brand: 'FastenPro', description: 'Grade 8.8 zinc plated', price: 1.20, stock: 5000, reserved: 200, category: 'fasteners' },
  { id: 13, sku: 'GN-WLD-001', name: 'Welding Rod 3.2mm', nameKa: 'შედუღების ელექტროდი 3.2მმ', brand: 'WeldMax', description: 'E7018 low hydrogen, 5kg pack', price: 28.00, stock: 350, reserved: 20, category: 'welding' },
  { id: 14, sku: 'GN-PRF-001', name: 'I-Beam HEB200', nameKa: 'ი-ძელი HEB200', brand: 'SteelBuild', description: 'European wide flange beam, per meter', price: 95.00, stock: 400, reserved: 30, category: 'profiles' },
  { id: 15, sku: 'GN-PRF-002', name: 'Channel U120', nameKa: 'შველერი U120', brand: 'SteelBuild', description: 'Structural channel, per meter', price: 42.00, stock: 550, reserved: 25, category: 'profiles' },
  { id: 16, sku: 'GN-GSK-001', name: 'Gasket Set DN100', nameKa: 'პროკლადკის კომპლექტი DN100', brand: 'SealTech', description: 'EPDM flange gasket set', price: 12.50, stock: 200, reserved: 10, category: 'seals' },
  { id: 17, sku: 'GN-INS-001', name: 'Pipe Insulation DN80', nameKa: 'მილის იზოლაცია DN80', brand: 'ThermoWrap', description: 'Mineral wool, 50mm thick, per meter', price: 18.00, stock: 800, reserved: 40, category: 'insulation' },
  { id: 18, sku: 'GN-MNM-001', name: 'Pressure Gauge 0-10bar', nameKa: 'მანომეტრი 0-10ბარი', brand: 'GaugePro', description: 'SS case, glycerin filled', price: 65.00, stock: 30, reserved: 2, category: 'instruments' },
  { id: 19, sku: 'GN-EXP-001', name: 'Expansion Joint DN100', nameKa: 'კომპენსატორი DN100', brand: 'FlexPipe', description: 'Rubber expansion joint', price: 340.00, stock: 15, reserved: 1, category: 'expansion' },
  { id: 20, sku: 'GN-RED-001', name: 'Reducer DN100/80', nameKa: 'რედუქტორი DN100/80', brand: 'GN Steel', description: 'Concentric pipe reducer', price: 38.00, stock: 100, reserved: 4, category: 'fittings' },
  { id: 21, sku: 'GN-SPR-001', name: 'Sprinkler Head K80', nameKa: 'სპრინკლერის თავი K80', brand: 'FireSafe', description: 'Pendent type, 68°C', price: 22.00, stock: 500, reserved: 50, category: 'fire' },
  { id: 22, sku: 'GN-TNK-001', name: 'Storage Tank 5000L', nameKa: 'ავზი 5000ლ', brand: 'TankPro', description: 'Stainless steel storage tank', price: 8500.00, stock: 3, reserved: 1, category: 'tanks' },
  { id: 23, sku: 'GN-CMP-001', name: 'Air Compressor 7.5kW', nameKa: 'კომპრესორი 7.5kW', brand: 'AirTech', description: 'Screw type air compressor', price: 5200.00, stock: 4, reserved: 0, category: 'compressors' },
  { id: 24, sku: 'GN-HOS-001', name: 'Hydraulic Hose DN12', nameKa: 'ჰიდრავლიკური შლანგი DN12', brand: 'HoseMax', description: 'High pressure, per meter', price: 15.00, stock: 1000, reserved: 60, category: 'hoses' },
  { id: 25, sku: 'GN-FLT-001', name: 'Y-Strainer DN50', nameKa: 'Y-ფილტრი DN50', brand: 'FilterPro', description: 'Cast iron Y-type strainer', price: 145.00, stock: 25, reserved: 2, category: 'filters' }
];

export const CUSTOMERS = [
  { id: 1, name: 'შპს "აქვა სისტემს"', nameEn: 'Aqua Systems LLC', taxId: '405123456', address: 'თბილისი, რუსთაველის 28', phone: '+995 577 111 222', email: 'aqua@systems.ge', totalSpent: 125800, invoiceCount: 18, outstanding: 12500 },
  { id: 2, name: 'შპს "ჰაიდრო ტექ"', nameEn: 'Hydro Tech LLC', taxId: '405234567', address: 'ბათუმი, ჭავჭავაძის 15', phone: '+995 555 222 333', email: 'info@hydrotech.ge', totalSpent: 89500, invoiceCount: 12, outstanding: 0 },
  { id: 3, name: 'სს "თბილისი ენერჯი"', nameEn: 'Tbilisi Energy JSC', taxId: '204345678', address: 'თბილისი, მარჯანიშვილის 5', phone: '+995 322 123 456', email: 'procurement@energy.ge', totalSpent: 345200, invoiceCount: 35, outstanding: 45000 },
  { id: 4, name: 'შპს "მშენებელი+"', nameEn: 'Builder+ LLC', taxId: '405456789', address: 'ქუთაისი, ნინოშვილის 42', phone: '+995 599 333 444', email: 'builder@plus.ge', totalSpent: 67300, invoiceCount: 8, outstanding: 5200 },
  { id: 5, name: 'ი/მ ზაზა მამულაშვილი', nameEn: 'Zaza Mamulashvili IE', taxId: '600567890', address: 'რუსთავი, მშვიდობის 12', phone: '+995 571 444 555', email: 'zaza@gmail.com', totalSpent: 23400, invoiceCount: 5, outstanding: 0 },
  { id: 6, name: 'შპს "პაიპ ლაინ"', nameEn: 'Pipe Line LLC', taxId: '405678901', address: 'თბილისი, წერეთლის 88', phone: '+995 555 555 666', email: 'sales@pipeline.ge', totalSpent: 198700, invoiceCount: 22, outstanding: 18900 },
  { id: 7, name: 'შპს "ინდუსტრია XXI"', nameEn: 'Industry XXI LLC', taxId: '405789012', address: 'თბილისი, კახეთის გზ. 22', phone: '+995 577 666 777', email: 'office@industry21.ge', totalSpent: 156800, invoiceCount: 15, outstanding: 8700 },
  { id: 8, name: 'სს "ჯორჯიან ვოტერ"', nameEn: 'Georgian Water JSC', taxId: '204890123', address: 'თბილისი, კოსტავას 52', phone: '+995 322 567 890', email: 'supply@gwater.ge', totalSpent: 567000, invoiceCount: 42, outstanding: 67800 },
  { id: 9, name: 'შპს "მეტალ სერვისი"', nameEn: 'Metal Service LLC', taxId: '405901234', address: 'გორი, სტალინის 7', phone: '+995 599 777 888', email: 'metal@service.ge', totalSpent: 78900, invoiceCount: 9, outstanding: 0 },
  { id: 10, name: 'შპს "სანტექ პროფი"', nameEn: 'Santech Profi LLC', taxId: '406012345', address: 'თბილისი, ვაზისუბნის 3', phone: '+995 555 888 999', email: 'info@santechprofi.ge', totalSpent: 45600, invoiceCount: 7, outstanding: 3200 },
  { id: 11, name: 'შპს "აგრო ტექნოლოჯი"', nameEn: 'Agro Technology LLC', taxId: '405112233', address: 'მარნეული, რუსთაველის 1', phone: '+995 577 999 000', email: 'agro@technology.ge', totalSpent: 34500, invoiceCount: 4, outstanding: 0 },
  { id: 12, name: 'შპს "კონსტრუქცია"', nameEn: 'Construction LLC', taxId: '405223344', address: 'თბილისი, აღმაშენებლის 150', phone: '+995 599 000 111', email: 'const@ruction.ge', totalSpent: 234500, invoiceCount: 28, outstanding: 32100 },
  { id: 13, name: 'ი/მ გიორგი ხელაძე', nameEn: 'Giorgi Kheladze IE', taxId: '600334455', address: 'ზუგდიდი, ჯავახიშვილის 9', phone: '+995 571 111 000', email: 'gkheladze@mail.ge', totalSpent: 12800, invoiceCount: 3, outstanding: 1500 },
  { id: 14, name: 'შპს "ფლოუ ტექ"', nameEn: 'Flow Tech LLC', taxId: '405445566', address: 'თბილისი, ცოტნე დადიანის 24', phone: '+995 555 012 345', email: 'info@flowtech.ge', totalSpent: 189400, invoiceCount: 20, outstanding: 15600 },
  { id: 15, name: 'შპს "გრინ ბილდ"', nameEn: 'Green Build LLC', taxId: '405556677', address: 'ბათუმი, გოგებაშვილის 18', phone: '+995 577 234 567', email: 'green@build.ge', totalSpent: 56700, invoiceCount: 6, outstanding: 0 }
];

function d(daysAgo) {
  const date = new Date();
  date.setDate(date.getDate() - daysAgo);
  return date.toISOString().split('T')[0];
}

export const INVOICES = [
  // ========= SOLD (fully paid · all items sold · soldDate set) =========
  {
    id: 1, number: 'GN-1001', customerId: 1, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 1, qty: 50, price: 85.50, itemStatus: 'sold', warranty: '12 months', reservationDays: 0 },
      { productId: 5, qty: 100, price: 32.00, itemStatus: 'sold', warranty: '', reservationDays: 0 },
      { productId: 7, qty: 20, price: 45.00, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(58), method: 'company_transfer', amount: 5000 },
      { date: d(55), method: 'company_transfer', amount: 3375 }
    ],
    totalAmount: 8375, paidAmount: 8375, createdAt: d(60), saleDate: null, soldDate: d(55),
    authorId: 1, generalNote: 'მუდმივი მომხმარებელი, პრიორიტეტული შეკვეთა', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: 'გადახდა სრულად მიღებულია', consultantNote: 'VIP კლიენტი'
  },
  {
    id: 2, number: 'GN-1002', customerId: 6, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 2, qty: 80, price: 125.00, itemStatus: 'sold', warranty: '', reservationDays: 0 },
      { productId: 14, qty: 150, price: 95.00, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(48), method: 'company_transfer', amount: 15000 },
      { date: d(45), method: 'company_transfer', amount: 9250 }
    ],
    totalAmount: 24250, paidAmount: 24250, createdAt: d(50), saleDate: null, soldDate: d(45),
    authorId: 1, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: true,
    accountantNote: 'კორექტირება - ფასდაკლება გამოყენებულია', consultantNote: ''
  },
  {
    id: 3, number: 'GN-1003', customerId: 4, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 3, qty: 10, price: 230.00, itemStatus: 'sold', warranty: '12 months', reservationDays: 0 },
      { productId: 4, qty: 30, price: 78.50, itemStatus: 'sold', warranty: '12 months', reservationDays: 0 }
    ],
    payments: [
      { date: d(68), method: 'cash', amount: 4655 }
    ],
    totalAmount: 4655, paidAmount: 4655, createdAt: d(70), saleDate: null, soldDate: d(68),
    authorId: 4, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 4, number: 'GN-1004', customerId: 5, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 4, qty: 15, price: 78.50, itemStatus: 'sold', warranty: '12 months', reservationDays: 0 },
      { productId: 20, qty: 10, price: 38.00, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(73), method: 'cash', amount: 1557.50 }
    ],
    totalAmount: 1557.50, paidAmount: 1557.50, createdAt: d(75), saleDate: null, soldDate: d(73),
    authorId: 4, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 5, number: 'GN-1005', customerId: 9, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 10, qty: 50, price: 185.00, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(42), method: 'company_transfer', amount: 9250 }
    ],
    totalAmount: 9250, paidAmount: 9250, createdAt: d(45), saleDate: null, soldDate: d(42),
    authorId: 3, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 6, number: 'GN-1006', customerId: 15, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 24, qty: 200, price: 15.00, itemStatus: 'sold', warranty: '', reservationDays: 0 },
      { productId: 7, qty: 30, price: 45.00, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(52), method: 'company_transfer', amount: 4350 }
    ],
    totalAmount: 4350, paidAmount: 4350, createdAt: d(55), saleDate: null, soldDate: d(52),
    authorId: 2, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 7, number: 'GN-1007', customerId: 13, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 4, qty: 20, price: 78.50, itemStatus: 'sold', warranty: '12 months', reservationDays: 0 }
    ],
    payments: [
      { date: d(83), method: 'cash', amount: 1570 }
    ],
    totalAmount: 1570, paidAmount: 1570, createdAt: d(85), saleDate: null, soldDate: d(83),
    authorId: 4, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 8, number: 'GN-1008', customerId: 7, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 15, qty: 100, price: 42.00, itemStatus: 'sold', warranty: '', reservationDays: 0 },
      { productId: 12, qty: 3000, price: 1.20, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(32), method: 'company_transfer', amount: 7800 }
    ],
    totalAmount: 7800, paidAmount: 7800, createdAt: d(35), saleDate: null, soldDate: d(32),
    authorId: 4, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 9, number: 'GN-1009', customerId: 10, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 13, qty: 100, price: 28.00, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(26), method: 'other', amount: 2800 }
    ],
    totalAmount: 2800, paidAmount: 2800, createdAt: d(28), saleDate: null, soldDate: d(26),
    authorId: 4, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 10, number: 'GN-1010', customerId: 2, status: 'standard', lifecycleStatus: 'sold',
    items: [
      { productId: 25, qty: 6, price: 145.00, itemStatus: 'sold', warranty: '12 months', reservationDays: 0 },
      { productId: 16, qty: 12, price: 12.50, itemStatus: 'sold', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(35), method: 'company_transfer', amount: 1020 }
    ],
    totalAmount: 1020, paidAmount: 1020, createdAt: d(38), saleDate: null, soldDate: d(35),
    authorId: 2, generalNote: '', isRsUploaded: true, isCreditChecked: true, isReceiptChecked: true, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  // ========= ACTIVE / RESERVED (partial payment · items reserved · no soldDate) =========
  {
    id: 11, number: 'GN-1011', customerId: 3, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 8, qty: 2, price: 2850.00, itemStatus: 'reserved', warranty: '24 months', reservationDays: 14 },
      { productId: 9, qty: 3, price: 1950.00, itemStatus: 'reserved', warranty: '24 months', reservationDays: 14 }
    ],
    payments: [
      { date: d(23), method: 'company_transfer', amount: 5700 }
    ],
    totalAmount: 11550, paidAmount: 5700, createdAt: d(25), saleDate: null, soldDate: null,
    authorId: 2, generalNote: 'ტუმბოების პარტია, ეტაპობრივი მიწოდება', isRsUploaded: true, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'კლიენტი მოითხოვს ეტაპობრივ მიწოდებას'
  },
  {
    id: 12, number: 'GN-1012', customerId: 8, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 1, qty: 200, price: 85.50, itemStatus: 'reserved', warranty: '', reservationDays: 30 },
      { productId: 2, qty: 100, price: 125.00, itemStatus: 'reserved', warranty: '', reservationDays: 30 },
      { productId: 5, qty: 500, price: 32.00, itemStatus: 'reserved', warranty: '', reservationDays: 30 },
      { productId: 6, qty: 200, price: 55.00, itemStatus: 'reserved', warranty: '', reservationDays: 30 }
    ],
    payments: [
      { date: d(18), method: 'company_transfer', amount: 30000 },
      { date: d(8), method: 'company_transfer', amount: 15000 }
    ],
    totalAmount: 56600, paidAmount: 45000, createdAt: d(20), saleDate: null, soldDate: null,
    authorId: 1, generalNote: 'დიდი პარტია, მიწოდება ეტაპობრივად', isRsUploaded: true, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: 'ნაშთი 11600 ლარი', consultantNote: 'მთავარი კონტრაქტი Q1'
  },
  {
    id: 13, number: 'GN-1013', customerId: 14, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 8, qty: 1, price: 2850.00, itemStatus: 'reserved', warranty: '24 months', reservationDays: 7 },
      { productId: 18, qty: 5, price: 65.00, itemStatus: 'reserved', warranty: '12 months', reservationDays: 0 }
    ],
    payments: [
      { date: d(9), method: 'company_transfer', amount: 1500 }
    ],
    totalAmount: 3175, paidAmount: 1500, createdAt: d(10), saleDate: null, soldDate: null,
    authorId: 2, generalNote: 'ტუმბო რეზერვაციაში 7 დღით', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ტუმბოს მიწოდება კვირის ბოლომდე'
  },
  {
    id: 14, number: 'GN-1014', customerId: 3, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 1, qty: 300, price: 85.50, itemStatus: 'reserved', warranty: '', reservationDays: 21 },
      { productId: 6, qty: 150, price: 55.00, itemStatus: 'reserved', warranty: '', reservationDays: 21 }
    ],
    payments: [
      { date: d(13), method: 'company_transfer', amount: 15000 },
      { date: d(5), method: 'other', amount: 5000 }
    ],
    totalAmount: 33900, paidAmount: 20000, createdAt: d(15), saleDate: null, soldDate: null,
    authorId: 1, generalNote: '', isRsUploaded: true, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ეტაპობრივი მიწოდება'
  },
  {
    id: 15, number: 'GN-1015', customerId: 8, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 22, qty: 1, price: 8500.00, itemStatus: 'reserved', warranty: '36 months', reservationDays: 30 },
      { productId: 19, qty: 3, price: 340.00, itemStatus: 'reserved', warranty: '12 months', reservationDays: 0 }
    ],
    payments: [
      { date: d(5), method: 'company_transfer', amount: 5000 }
    ],
    totalAmount: 9520, paidAmount: 5000, createdAt: d(6), saleDate: null, soldDate: null,
    authorId: 2, generalNote: 'ავზი რეზერვაცია 30 დღით', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ავზის მიწოდება მარტში'
  },
  {
    id: 16, number: 'GN-1016', customerId: 14, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 2, qty: 60, price: 125.00, itemStatus: 'reserved', warranty: '', reservationDays: 0 },
      { productId: 20, qty: 30, price: 38.00, itemStatus: 'reserved', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(4), method: 'company_transfer', amount: 5000 },
      { date: d(2), method: 'credit', amount: 2640 }
    ],
    totalAmount: 8640, paidAmount: 7640, createdAt: d(5), saleDate: null, soldDate: null,
    authorId: 2, generalNote: '', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 17, number: 'GN-1017', customerId: 3, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 24, qty: 500, price: 15.00, itemStatus: 'reserved', warranty: '', reservationDays: 0 },
      { productId: 13, qty: 200, price: 28.00, itemStatus: 'reserved', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(6), method: 'company_transfer', amount: 10000 },
      { date: d(2), method: 'consignment', amount: 3100 }
    ],
    totalAmount: 13100, paidAmount: 10000, createdAt: d(8), saleDate: null, soldDate: null,
    authorId: 3, generalNote: 'კონსიგნაცია - 3100 ლარი', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: 'კონსიგნაცია არ ითვლება', consultantNote: ''
  },
  // Reserved + fully paid (client paid in full, delivery pending — ready to mark as sold)
  {
    id: 25, number: 'GN-1025', customerId: 7, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 11, qty: 10, price: 320.00, itemStatus: 'reserved', warranty: '', reservationDays: 14 },
      { productId: 16, qty: 8, price: 12.50, itemStatus: 'reserved', warranty: '', reservationDays: 0 }
    ],
    payments: [
      { date: d(2), method: 'company_transfer', amount: 3300 }
    ],
    totalAmount: 3300, paidAmount: 3300, createdAt: d(3), saleDate: null, soldDate: null,
    authorId: 2, generalNote: 'სრულად გადახდილია, მიწოდება 2 კვირაში', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'გადახდა მიღებულია — მიწოდება რჩება'
  },
  // ========= ACTIVE / RESERVED =========
  {
    id: 18, number: 'GN-1018', customerId: 12, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 14, qty: 200, price: 95.00, itemStatus: 'reserved', warranty: '', reservationDays: 30 },
      { productId: 15, qty: 300, price: 42.00, itemStatus: 'reserved', warranty: '', reservationDays: 30 },
      { productId: 13, qty: 50, price: 28.00, itemStatus: 'reserved', warranty: '', reservationDays: 30 }
    ],
    payments: [
      { date: d(2), method: 'company_transfer', amount: 10000 }
    ],
    totalAmount: 33000, paidAmount: 10000, createdAt: d(3), saleDate: null, soldDate: null,
    authorId: 1, generalNote: 'სამშენებლო პროექტის შეკვეთა', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ავანსი მიღებულია — დარჩენილი გადახდა მიწოდებისას'
  },
  {
    id: 19, number: 'GN-1019', customerId: 1, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 9, qty: 2, price: 1950.00, itemStatus: 'reserved', warranty: '24 months', reservationDays: 14 },
      { productId: 18, qty: 3, price: 65.00, itemStatus: 'reserved', warranty: '12 months', reservationDays: 14 }
    ],
    payments: [
      { date: d(1), method: 'cash', amount: 2000 }
    ],
    totalAmount: 4095, paidAmount: 2000, createdAt: d(2), saleDate: null, soldDate: null,
    authorId: 1, generalNote: '', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ნაწილობრივ გადახდილია — ელოდება დარჩენილ თანხას'
  },
  // ========= CANCELED =========
  {
    id: 20, number: 'GN-1020', customerId: 4, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 8, qty: 1, price: 2850.00, itemStatus: 'canceled', warranty: '', reservationDays: 0 }
    ],
    payments: [],
    totalAmount: 2850, paidAmount: 0, createdAt: d(4), saleDate: null, soldDate: null,
    authorId: 3, generalNote: 'კლიენტმა გააუქმა შეკვეთა', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ანულირება - კლიენტი ბიუჯეტს ითვლის'
  },
  // ========= PARTIAL CANCEL — Refund Due demo =========
  {
    id: 26, number: 'GN-1026', customerId: 7, status: 'standard', lifecycleStatus: 'active',
    items: [
      { productId: 3,  qty: 5,  price: 230.00, itemStatus: 'canceled', warranty: '',          reservationDays: 0  },
      { productId: 5,  qty: 20, price: 32.00,  itemStatus: 'reserved', warranty: '',          reservationDays: 14 },
      { productId: 7,  qty: 10, price: 45.00,  itemStatus: 'reserved', warranty: '12 months', reservationDays: 14 }
    ],
    payments: [
      { date: d(3), method: 'company_transfer', amount: 2240 },
      { date: d(1), method: 'refund',            amount: -1150 }
    ],
    totalAmount: 1090, paidAmount: 1090, createdAt: d(5), saleDate: d(5), soldDate: null,
    authorId: 2, generalNote: 'კლიენტმა სარქვლები გააუქმა — ნაწილობრივი გაუქმება, დარჩენილი ნივთები რეზერვაციაშია', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: 'გადახდა 2240 — გამოქვითვა 1150 (გაუქმებული სარქვლები) — ნეტო 1090 — ანაზღაურება დასრულდა', consultantNote: 'სარქვლები გაუქმდა კლიენტის მოთხოვნით'
  },
  // ========= FICTIVE (status=fictive · all items none · no payments) =========
  {
    id: 21, number: 'GN-1021', customerId: 2, status: 'fictive', lifecycleStatus: 'draft',
    items: [
      { productId: 22, qty: 1, price: 8500.00, itemStatus: 'none', warranty: '36 months', reservationDays: 0 },
      { productId: 23, qty: 1, price: 5200.00, itemStatus: 'none', warranty: '24 months', reservationDays: 0 }
    ],
    payments: [],
    totalAmount: 13700, paidAmount: 0, createdAt: d(7), saleDate: null, soldDate: null,
    authorId: 3, generalNote: 'კლიენტი განიხილავს შემოთავაზებას', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ფასზე მოლაპარაკება მიმდინარეობს'
  },
  {
    id: 22, number: 'GN-1022', customerId: 10, status: 'fictive', lifecycleStatus: 'draft',
    items: [
      { productId: 19, qty: 5, price: 340.00, itemStatus: 'none', warranty: '12 months', reservationDays: 0 },
      { productId: 16, qty: 20, price: 12.50, itemStatus: 'none', warranty: '', reservationDays: 0 }
    ],
    payments: [],
    totalAmount: 1950, paidAmount: 0, createdAt: d(4), saleDate: null, soldDate: null,
    authorId: 3, generalNote: 'შეთავაზება - ვალიდურია 15 დღე', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: ''
  },
  {
    id: 23, number: 'GN-1023', customerId: 11, status: 'fictive', lifecycleStatus: 'draft',
    items: [
      { productId: 17, qty: 200, price: 18.00, itemStatus: 'none', warranty: '', reservationDays: 0 },
      { productId: 21, qty: 100, price: 22.00, itemStatus: 'none', warranty: '', reservationDays: 0 }
    ],
    payments: [],
    totalAmount: 5800, paidAmount: 0, createdAt: d(2), saleDate: null, soldDate: null,
    authorId: 4, generalNote: 'სასოფლო-სამეურნეო პროექტისთვის', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'მოელოდება დაფინანსებას'
  },
  {
    id: 24, number: 'GN-1024', customerId: 6, status: 'fictive', lifecycleStatus: 'draft',
    items: [
      { productId: 23, qty: 2, price: 5200.00, itemStatus: 'none', warranty: '24 months', reservationDays: 0 },
      { productId: 9, qty: 2, price: 1950.00, itemStatus: 'none', warranty: '24 months', reservationDays: 0 }
    ],
    payments: [],
    totalAmount: 14300, paidAmount: 0, createdAt: d(1), saleDate: null, soldDate: null,
    authorId: 3, generalNote: 'ტენდერის წინასწარი შეთავაზება', isRsUploaded: false, isCreditChecked: false, isReceiptChecked: false, isCorrected: false,
    accountantNote: '', consultantNote: 'ტენდერის ვადა - 1 მარტი'
  }
];

export const DEPOSITS = [
  { id: 1, date: d(60), amount: 50000, type: 'credit', note: 'საწყისი ბალანსი', customerId: null },
  { id: 2, date: d(50), amount: -8375, type: 'debit', note: 'ინვოისი GN-1001 - გადახდა', customerId: 1 },
  { id: 3, date: d(45), amount: 25000, type: 'credit', note: 'საბანკო შემოსავალი', customerId: null },
  { id: 4, date: d(40), amount: -24250, type: 'debit', note: 'ინვოისი GN-1003 - გადახდა', customerId: 6 },
  { id: 5, date: d(35), amount: 30000, type: 'credit', note: 'კლიენტის გადარიცხვა', customerId: 8 },
  { id: 6, date: d(25), amount: -5700, type: 'debit', note: 'ინვოისი GN-1002 - ნაწილობრივი', customerId: 3 },
  { id: 7, date: d(15), amount: 45000, type: 'credit', note: 'საბანკო შემოსავალი', customerId: null },
  { id: 8, date: d(10), amount: -20000, type: 'debit', note: 'მომწოდებლის გადახდა', customerId: null },
  { id: 9, date: d(5), amount: 15000, type: 'credit', note: 'კლიენტის გადარიცხვა', customerId: 12 },
  { id: 10, date: d(1), amount: -10000, type: 'debit', note: 'ოპერაციული ხარჯი', customerId: null }
];

export const OTHER_DELIVERIES = [];

export const PAYMENT_METHODS = {
  company_transfer: { label: 'Bank Transfer', labelKa: 'საბანკო გადარიცხვა', color: 'primary', i18nKey: 'label.bankTransfer' },
  cash:             { label: 'Cash',          labelKa: 'ნაღდი ფული',          color: 'success', i18nKey: 'label.cash'         },
  consignment:      { label: 'Consignment',   labelKa: 'კონსიგნაცია',         color: 'warning', i18nKey: 'label.consignment'  },
  credit:           { label: 'Credit',        labelKa: 'განვადება',            color: 'info',    i18nKey: 'label.credit'       },
  other:            { label: 'Other',         labelKa: 'სხვა',                 color: 'neutral', i18nKey: 'label.other'        },
  refund:           { label: 'Refund',        labelKa: 'თანხის დაბრუნება',     color: 'danger',  i18nKey: 'label.refund'       }
};

export const STATUS_LABELS = {
  fictive:  { label: 'Fictive',  labelKa: 'ფიქტიური',    color: 'warning', i18nKey: 'label.fictive'  },
  standard: { label: 'Standard', labelKa: 'სტანდარტული', color: 'primary', i18nKey: 'label.standard' }
};

export const LIFECYCLE_LABELS = {
  draft:    { label: 'Draft',    labelKa: 'პროექტი',    color: 'neutral', i18nKey: 'label.draft'    },
  sold:     { label: 'Sold',     labelKa: 'გაყიდული',   color: 'success', i18nKey: 'label.sold'     },
  reserved: { label: 'Reserved', labelKa: 'რეზერვაცია', color: 'warning', i18nKey: 'label.reserved' },
  canceled: { label: 'Canceled', labelKa: 'გაუქმებული', color: 'danger',  i18nKey: 'label.canceled' }
};

export const ROLE_LABELS = {
  admin:      { label: 'Admin',      labelKa: 'ადმინისტრატორი', color: 'danger',  i18nKey: 'label.admin'      },
  manager:    { label: 'Manager',    labelKa: 'მენეჯერი',        color: 'primary', i18nKey: 'label.manager'    },
  sales:      { label: 'Sales',      labelKa: 'გაყიდვები',       color: 'success', i18nKey: 'label.sales'      },
  accountant: { label: 'Accountant', labelKa: 'ბუღალტერი',       color: 'neutral', i18nKey: 'label.accountant' }
};

export const ITEM_STATUS_LABELS = {
  none:     { label: 'None',     labelKa: 'არცერთი',    color: 'neutral', i18nKey: 'label.none'     },
  sold:     { label: 'Sold',     labelKa: 'გაყიდული',   color: 'success', i18nKey: 'label.sold'     },
  reserved: { label: 'Reserved', labelKa: 'რეზერვაცია', color: 'warning', i18nKey: 'label.reserved' },
  canceled: { label: 'Canceled', labelKa: 'გაუქმებული', color: 'danger',  i18nKey: 'label.canceled' }
};

// Sum of non-canceled items × qty × price. Use this everywhere instead of inv.totalAmount
// so that partially or fully canceled invoices reflect only what's actually owed.
export function getEffectiveTotal(inv) {
  if (!inv || !inv.items) return 0
  return inv.items
    .filter(item => item.itemStatus !== 'canceled')
    .reduce((sum, item) => sum + item.qty * item.price, 0)
}

export function getInvoiceLifecycle(inv) {
  if (!inv) return LIFECYCLE_LABELS.draft
  // Fictive invoices always show Draft — no other status allowed
  if (inv.status === 'fictive') return LIFECYCLE_LABELS.draft
  const ls = inv.lifecycleStatus
  if (ls === 'draft') return LIFECYCLE_LABELS.draft
  // Backward compat: 'completed' → Sold
  if (ls === 'completed' || ls === 'sold') return LIFECYCLE_LABELS.sold
  // For 'active' or any other stored value: derive from items
  const items = inv.items || []
  if (items.length > 0 && items.every(it => it.itemStatus === 'sold')) return LIFECYCLE_LABELS.sold
  if (items.some(it => it.itemStatus === 'reserved')) return LIFECYCLE_LABELS.reserved
  if (items.some(it => it.itemStatus === 'canceled')) return LIFECYCLE_LABELS.canceled
  return LIFECYCLE_LABELS.reserved
}

export const WARRANTY_OPTIONS = [
  { value: '6_months', label: '6 თვე', labelEn: '6 Months' },
  { value: '1_year',   label: '1 წელი', labelEn: '1 Year' },
  { value: '2_years',  label: '2 წელი', labelEn: '2 Years' },
  { value: '3_years',  label: '3 წელი', labelEn: '3 Years' }
];
