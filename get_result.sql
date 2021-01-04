SELECT 
    T1.PostDate, T1.Author, T2.Elevator, T2.HouseType, T2.Floor, T2.TotalFloor, T2.City, T2.District, 
    CONCAT(T2.RentCost,' 萬') AS RentCost, T2.RentDesc, T1.Title, T3.Content, T1.Link 
FROM rent_apart T1 JOIN rent_apart_ext T2 ON T1.ID = T2.ID JOIN rent_apart_content T3 ON T1.ID = T3.ID
WHERE 
    T2.District IN ('中和區', '永和區', '新店區', '文山區')
    AND T2.Elevator = 'Yes'
    AND T2.RoomNum  IN ('', '3')
    AND T2.RentCost > 1.8
ORDER BY T2.City ASC, T2.District ASC, T2.RentCost ASC, T2.HouseType ASC