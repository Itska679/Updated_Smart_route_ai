from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Optional
from ortools.constraint_solver import routing_enums_pb2
from ortools.constraint_solver import pywrapcp
import math

app = FastAPI(title="Smart-Route AI Optimizer", version="1.0")

# Allow your PHP app to call this service
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# ============ SCHEMAS ============
class Location(BaseModel):
    id: str
    lat: float
    lng: float
    demand: int = 1
    time_window_start: Optional[int] = None  # minutes from midnight
    time_window_end: Optional[int] = None

class OptimizeRequest(BaseModel):
    depot: Location
    stops: List[Location]
    num_vehicles: int = 1
    vehicle_capacity: int = 50
    max_route_minutes: int = 480  # 8 hours

class RouteStop(BaseModel):
    id: str
    arrival_minute: int
    sequence: int

class RouteResult(BaseModel):
    vehicle_id: int
    stops: List[RouteStop]
    total_distance_km: float
    total_duration_minutes: int

class OptimizeResponse(BaseModel):
    routes: List[RouteResult]
    total_distance_km: float
    total_duration_minutes: int
    status: str


# ============ HELPERS ============
def haversine_km(lat1, lng1, lat2, lng2):
    """Great-circle distance in kilometers."""
    R = 6371.0
    phi1 = math.radians(lat1)
    phi2 = math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlambda = math.radians(lng2 - lng1)
    a = math.sin(dphi/2)**2 + math.cos(phi1)*math.cos(phi2)*math.sin(dlambda/2)**2
    return 2 * R * math.asin(math.sqrt(a))


def build_distance_matrix(locations):
    """Symmetric distance matrix (km) between all locations."""
    n = len(locations)
    matrix = [[0] * n for _ in range(n)]
    for i in range(n):
        for j in range(n):
            if i != j:
                matrix[i][j] = int(haversine_km(
                    locations[i].lat, locations[i].lng,
                    locations[j].lat, locations[j].lng
                ) * 1000)  # store in meters for integer math
    return matrix


def build_time_matrix(distance_matrix, avg_speed_kmh=25):
    """Approximate travel time matrix based on average urban speed."""
    time_matrix = []
    for row in distance_matrix:
        time_matrix.append([
            int((m / 1000.0) / avg_speed_kmh * 60)  # minutes
            for m in row
        ])
    return time_matrix


# ============ ROUTES ============
@app.get("/")
def root():
    return {
        "service": "Smart-Route AI Optimizer",
        "status": "running",
        "docs": "/docs"
    }


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/optimize", response_model=OptimizeResponse)
def optimize(req: OptimizeRequest):
    if len(req.stops) == 0:
        return OptimizeResponse(
            routes=[], total_distance_km=0,
            total_duration_minutes=0, status="no_stops"
        )

    # All locations: depot first, then stops
    all_locations = [req.depot] + req.stops
    n = len(all_locations)

    # Build matrices
    distance_matrix = build_distance_matrix(all_locations)
    time_matrix     = build_time_matrix(distance_matrix)

    # Capacity demands (depot has 0)
    demands = [0] + [s.demand for s in req.stops]

    # Time windows (depot: full day)
    time_windows = [(0, 24 * 60)]
    for s in req.stops:
        start = s.time_window_start if s.time_window_start is not None else 0
        end   = s.time_window_end   if s.time_window_end   is not None else 24 * 60
        time_windows.append((start, end))

    # OR-Tools setup
    manager = pywrapcp.RoutingIndexManager(n, req.num_vehicles, 0)
    routing = pywrapcp.RoutingModel(manager)

    # Distance callback
    def distance_callback(from_idx, to_idx):
        from_node = manager.IndexToNode(from_idx)
        to_node   = manager.IndexToNode(to_idx)
        return distance_matrix[from_node][to_node]

    transit_cb_idx = routing.RegisterTransitCallback(distance_callback)
    routing.SetArcCostEvaluatorOfAllVehicles(transit_cb_idx)

    # Capacity dimension
    def demand_callback(from_idx):
        from_node = manager.IndexToNode(from_idx)
        return demands[from_node]

    demand_cb_idx = routing.RegisterUnaryTransitCallback(demand_callback)
    routing.AddDimensionWithVehicleCapacity(
        demand_cb_idx, 0,
        [req.vehicle_capacity] * req.num_vehicles,
        True, "Capacity"
    )

    # Time dimension
    def time_callback(from_idx, to_idx):
        from_node = manager.IndexToNode(from_idx)
        to_node   = manager.IndexToNode(to_idx)
        return time_matrix[from_node][to_node]

    time_cb_idx = routing.RegisterTransitCallback(time_callback)
    routing.AddDimension(
        time_cb_idx, 30,  # 30-min slack
        req.max_route_minutes,
        False, "Time"
    )
    time_dimension = routing.GetDimensionOrDie("Time")

    # Time windows
    for idx, (start, end) in enumerate(time_windows):
        if idx == 0:
            continue  # skip depot
        index = manager.NodeToIndex(idx)
        time_dimension.CumulVar(index).SetRange(start, end)

    # Solve
    search_params = pywrapcp.DefaultRoutingSearchParameters()
    search_params.first_solution_strategy = (
        routing_enums_pb2.FirstSolutionStrategy.PATH_CHEAPEST_ARC
    )
    search_params.local_search_metaheuristic = (
        routing_enums_pb2.LocalSearchMetaheuristic.GUIDED_LOCAL_SEARCH
    )
    search_params.time_limit.seconds = 10

    solution = routing.SolveWithParameters(search_params)

    if not solution:
        raise HTTPException(status_code=422, detail="No feasible solution found.")

    # Extract routes
    routes = []
    total_dist_m = 0
    total_dur_min = 0

    for v in range(req.num_vehicles):
        index = routing.Start(v)
        stops_out = []
        route_dist_m = 0
        route_dur_min = 0
        seq = 0

        while not routing.IsEnd(index):
            node = manager.IndexToNode(index)
            arrival = solution.Min(time_dimension.CumulVar(index))

            if node != 0:  # skip depot
                stops_out.append(RouteStop(
                    id=all_locations[node].id,
                    arrival_minute=int(arrival),
                    sequence=seq
                ))
                seq += 1

            next_index = solution.Value(routing.NextVar(index))
            route_dist_m += routing.GetArcCostForVehicle(index, next_index, v)
            index = next_index

        # Final leg back to depot
        route_dur_min = int(solution.Min(time_dimension.CumulVar(routing.End(v))))

        if stops_out:
            routes.append(RouteResult(
                vehicle_id=v + 1,
                stops=stops_out,
                total_distance_km=round(route_dist_m / 1000.0, 2),
                total_duration_minutes=route_dur_min
            ))
            total_dist_m += route_dist_m
            total_dur_min += route_dur_min

    return OptimizeResponse(
        routes=routes,
        total_distance_km=round(total_dist_m / 1000.0, 2),
        total_duration_minutes=total_dur_min,
        status="ok"
    )