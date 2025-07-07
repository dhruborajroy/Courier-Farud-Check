# Courier-Farud-Check
🚨 Courier Fraud Check System (PHP + JSON API + Beautiful UI) A smart and efficient Courier Fraud Detection tool with a clean PHP-based backend and a responsive frontend UI. It helps businesses track delivery performance by analyzing courier activity and detecting suspicious behavior using JSON data reports.
🖼️ Sample JSON Response
{
  "phoneNumber": "0170595xxx",
  "totalOrders": 10,
  "totalDeliveries": 9,
  "totalCancellations": 1,
  "successRatio": 90,
  "couriers": [
    {
      "name": "Steadfast",
      "logo": "https://i.ibb.co.com/tM68nWR/stead-fast.png",
      "success": 6,
      "cancel": 0,
      "total": 6
    },
    {
      "name": "RedX",
      "logo": "https://i.ibb.co.com/NWL7Tr4/redx.png",
      "success": 3,
      "cancel": 1,
      "total": 4
    }
  ],
  "reports": [],
  "errors": []
}
